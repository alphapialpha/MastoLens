<?php

namespace App\Http\Controllers;

use App\Models\TrackedAccount;
use App\Services\MastodonApiService;
use App\Services\WebFingerService;
use Illuminate\Http\Request;

class TrackedAccountController extends Controller
{
    public function index()
    {
        $accounts = auth()->user()->trackedAccounts()
            ->orderByDesc('created_at')
            ->get();

        return view('tracked-accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('tracked-accounts.create');
    }

    public function store(Request $request, WebFingerService $webFinger, MastodonApiService $mastodon)
    {
        $request->validate([
            'handle' => ['required', 'string', 'max:255', 'regex:/^@?[a-zA-Z0-9_]+@[a-zA-Z0-9._-]+\.[a-zA-Z]{2,}$/'],
        ]);

        $parsed = $webFinger->parseHandle($request->input('handle'));
        if (!$parsed) {
            return back()->withErrors(['handle' => 'Invalid Mastodon handle format. Use user@instance.tld'])->withInput();
        }

        // Check for duplicate
        $existing = auth()->user()->trackedAccounts()
            ->where('acct_normalized', $parsed['acct_normalized'])
            ->first();

        if ($existing) {
            return back()->withErrors(['handle' => 'You are already tracking this account.'])->withInput();
        }

        // Resolve via WebFinger
        $resolved = $webFinger->resolve($parsed['username'], $parsed['instance_domain']);
        if (!$resolved || !$resolved['profile_url']) {
            return back()->withErrors(['handle' => 'Could not resolve this account. Check the handle and ensure the instance is reachable.'])->withInput();
        }

        // Look up account via Mastodon API
        $accountData = $mastodon->lookupAccount($parsed['username'], $parsed['instance_domain']);
        if (!$accountData || empty($accountData['id'])) {
            return back()->withErrors(['handle' => 'Could not find this account on the instance. It may not exist or the instance may be unavailable.'])->withInput();
        }

        $trackedAccount = auth()->user()->trackedAccounts()->create([
            'acct_input' => $request->input('handle'),
            'username' => $parsed['username'],
            'instance_domain' => $parsed['instance_domain'],
            'acct_normalized' => $parsed['acct_normalized'],
            'remote_account_id' => (string) $accountData['id'],
            'account_url' => $accountData['url'] ?? $resolved['profile_url'],
            'display_name' => $accountData['display_name'] ?? $parsed['username'],
            'avatar_url' => $accountData['avatar_static'] ?? $accountData['avatar'] ?? null,
            'note_html' => $accountData['note'] ?? null,
            'followers_count_latest' => $accountData['followers_count'] ?? null,
            'following_count_latest' => $accountData['following_count'] ?? null,
            'statuses_count_latest' => $accountData['statuses_count'] ?? null,
            'last_status_at_remote' => isset($accountData['last_status_at']) ? $accountData['last_status_at'] : null,
            'is_active' => true,
            'last_resolved_at' => now(),
        ]);

        return redirect()->route('tracked-accounts.show', $trackedAccount)
            ->with('status', 'Account added and tracking started.');
    }

    public function show(TrackedAccount $trackedAccount)
    {
        // Authorization: user can only view their own
        if ($trackedAccount->user_id !== auth()->id()) {
            abort(403);
        }

        $filter = request()->query('filter', 'all');
        $range = request()->query('range', 'all');

        $statusQuery = $trackedAccount->statuses()
            ->where('tracking_state', 'active')
            ->with('summary')
            ->orderByDesc('created_at_remote');

        // Time range filter
        $statusQuery = match ($range) {
            '2h' => $statusQuery->where('created_at_remote', '>=', now()->subHours(2)),
            '24h' => $statusQuery->where('created_at_remote', '>=', now()->subHours(24)),
            '7d' => $statusQuery->where('created_at_remote', '>=', now()->subDays(7)),
            '30d' => $statusQuery->where('created_at_remote', '>=', now()->subDays(30)),
            default => $statusQuery,
        };

        $statusQuery = match ($filter) {
            'originals' => $statusQuery->where('is_boost', false)->where('is_reply', false),
            'replies' => $statusQuery->where('is_reply', true),
            'boosts' => $statusQuery->where('is_boost', true),
            'media' => $statusQuery->where('has_media', true),
            default => $statusQuery,
        };

        $statuses = $statusQuery->limit(50)->get();

        // Account-level summary stats
        $allStatuses = $trackedAccount->statuses()->with('summary')->get();
        $originalsWithSummary = $allStatuses->filter(fn ($s) => $s->summary !== null && !$s->is_boost && !$s->is_reply);
        $totalStatuses = $allStatuses->count();

        $accountStats = [
            'total' => $totalStatuses,
            'active' => $allStatuses->where('tracking_state', 'active')->count(),
            'archived' => $allStatuses->where('tracking_state', 'archived')->count(),
            'failed' => $allStatuses->where('tracking_state', 'failed')->count(),
            'avg_favourites' => $originalsWithSummary->count() > 0
                ? round($originalsWithSummary->avg(fn ($s) => $s->summary->latest_favourites_count), 1)
                : 0,
            'avg_boosts' => $originalsWithSummary->count() > 0
                ? round($originalsWithSummary->avg(fn ($s) => $s->summary->latest_boosts_count), 1)
                : 0,
            'avg_replies' => $originalsWithSummary->count() > 0
                ? round($originalsWithSummary->avg(fn ($s) => $s->summary->latest_replies_count), 1)
                : 0,
        ];

        // Account metric history for followers chart
        $followerChartData = $trackedAccount->accountMetricSnapshots()
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($snap) => [
                'date' => $snap->snapshot_date->format('M j'),
                'followers' => $snap->followers_count,
                'following' => $snap->following_count,
            ])
            ->values();

        // Failed statuses for this account
        $failedStatuses = $trackedAccount->statuses()
            ->where('tracking_state', 'failed')
            ->with('summary')
            ->orderByDesc('failed_at')
            ->get();

        // Top 5 statuses by engagement
        $topStatuses = $trackedAccount->statuses()
            ->whereHas('summary')
            ->with('summary')
            ->where('tracking_state', 'active')
            ->get()
            ->sortByDesc(fn ($s) => $s->summary->latestTotalEngagement())
            ->take(5);

        return view('tracked-accounts.show', compact('trackedAccount', 'statuses', 'filter', 'range', 'accountStats', 'followerChartData', 'failedStatuses', 'topStatuses'));
    }

    public function archive(TrackedAccount $trackedAccount)
    {
        if ($trackedAccount->user_id !== auth()->id()) {
            abort(403);
        }

        $filter = request()->query('filter', 'all');

        $statusQuery = $trackedAccount->statuses()
            ->where('tracking_state', 'archived')
            ->with('summary')
            ->orderByDesc('created_at_remote');

        $statusQuery = match ($filter) {
            'originals' => $statusQuery->where('is_boost', false)->where('is_reply', false),
            'replies' => $statusQuery->where('is_reply', true),
            'boosts' => $statusQuery->where('is_boost', true),
            'media' => $statusQuery->where('has_media', true),
            default => $statusQuery,
        };

        $statuses = $statusQuery->paginate(10);

        // Group by month for display
        $groupedStatuses = $statuses->getCollection()->groupBy(fn ($s) => $s->created_at_remote?->format('F Y') ?? 'Unknown');

        $archivedCount = $trackedAccount->statuses()->where('tracking_state', 'archived')->count();

        return view('tracked-accounts.archive', compact('trackedAccount', 'statuses', 'groupedStatuses', 'filter', 'archivedCount'));
    }

    public function toggleActive(TrackedAccount $trackedAccount)
    {
        if ($trackedAccount->user_id !== auth()->id()) {
            abort(403);
        }

        $trackedAccount->update(['is_active' => !$trackedAccount->is_active]);

        $status = $trackedAccount->is_active ? 'Tracking enabled.' : 'Tracking paused.';

        return back()->with('status', $status);
    }

    public function destroy(TrackedAccount $trackedAccount)
    {
        if ($trackedAccount->user_id !== auth()->id()) {
            abort(403);
        }

        $trackedAccount->delete();

        return redirect()->route('tracked-accounts.index')
            ->with('status', 'Account removed.');
    }
}
