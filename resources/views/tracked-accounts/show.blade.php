@extends('layouts.app')

@section('content')
    <div class="mb-4">
        <a href="{{ route('dashboard') }}" class="text-sm text-brand-dark hover:text-brand-deep">&larr; Back to Dashboard</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                @if($trackedAccount->avatar_url)
                    <img src="{{ $trackedAccount->avatar_url }}" alt="" class="w-16 h-16 rounded-full">
                @else
                    <div class="w-16 h-16 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 text-xl font-bold">
                        {{ strtoupper(substr($trackedAccount->username, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <h1 class="text-2xl font-bold">{{ $trackedAccount->display_name ?: $trackedAccount->username }}</h1>
                    <p class="text-gray-500">
                        <a href="{{ $trackedAccount->account_url }}" target="_blank" rel="noopener" class="hover:text-brand-dark">
                            {{ $trackedAccount->acct_normalized }}
                        </a>
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                @if($trackedAccount->is_active)
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-800">Active</span>
                @else
                    <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold bg-gray-100 text-gray-800">Paused</span>
                @endif
                <form method="POST" action="{{ route('tracked-accounts.toggle', $trackedAccount) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-semibold {{ $trackedAccount->is_active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}">
                        {{ $trackedAccount->is_active ? 'Pause Tracking' : 'Resume Tracking' }}
                    </button>
                </form>
            </div>
        </div>

        @if($trackedAccount->note_html)
            <div class="mt-4 text-sm text-gray-600">
                {{ strip_tags($trackedAccount->note_html) }}
            </div>
        @endif

        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <div class="text-xl font-bold text-gray-900">{{ $trackedAccount->followers_count_latest !== null ? number_format($trackedAccount->followers_count_latest) : '—' }}</div>
                <div class="text-xs text-gray-500">Followers</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <div class="text-xl font-bold text-gray-900">{{ $trackedAccount->following_count_latest !== null ? number_format($trackedAccount->following_count_latest) : '—' }}</div>
                <div class="text-xs text-gray-500">Following</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <div class="text-xl font-bold text-gray-900">{{ $trackedAccount->statuses_count_latest !== null ? number_format($trackedAccount->statuses_count_latest) : '—' }}</div>
                <div class="text-xs text-gray-500">Posts (Remote)</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <div class="text-xl font-bold text-gray-900">{{ $accountStats['total'] }}</div>
                <div class="text-xs text-gray-500">Tracked Statuses</div>
            </div>
        </div>

        {{-- Account-level summary stats --}}
        <div class="mt-4 grid grid-cols-4 md:grid-cols-8 gap-4">
            <div class="bg-brand-pink/10 rounded-lg p-3 text-center">
                <div class="text-lg font-bold text-brand-dark">{{ $accountStats['active'] }}</div>
                <div class="text-xs text-brand-pink">Active</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3 text-center">
                <div class="text-lg font-bold text-gray-700">{{ $accountStats['archived'] }}</div>
                <div class="text-xs text-gray-500">Archived</div>
            </div>
            <div class="rounded-lg p-3 text-center {{ $accountStats['failed'] > 0 ? 'bg-red-50' : 'bg-gray-50' }}">
                <div class="text-lg font-bold {{ $accountStats['failed'] > 0 ? 'text-red-700' : 'text-gray-700' }}">{{ $accountStats['failed'] }}</div>
                <div class="text-xs {{ $accountStats['failed'] > 0 ? 'text-red-500' : 'text-gray-500' }}">Failed</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-3 text-center">
                <div class="text-lg font-bold text-yellow-700">{{ $accountStats['avg_favourites'] }}</div>
                <div class="text-xs text-yellow-600">Avg ⭐</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-3 text-center">
                <div class="text-lg font-bold text-blue-700">{{ $accountStats['avg_boosts'] }}</div>
                <div class="text-xs text-blue-600">Avg 🔁</div>
            </div>
            <div class="bg-green-50 rounded-lg p-3 text-center">
                <div class="text-lg font-bold text-green-700">{{ $accountStats['avg_replies'] }}</div>
                <div class="text-xs text-green-600">Avg 💬</div>
            </div>
            <div class="bg-purple-50 rounded-lg p-3 text-center">
                <div class="text-lg font-bold text-purple-700">{{ $accountStats['avg_quotes'] }}</div>
                <div class="text-xs text-purple-600">Avg ❝</div>
            </div>
            <div class="bg-gray-100 rounded-lg p-3 text-center">
                <div class="text-lg font-bold text-gray-700">{{ round($accountStats['avg_favourites'] + $accountStats['avg_boosts'] + $accountStats['avg_replies'] + $accountStats['avg_quotes'], 1) }}</div>
                <div class="text-xs text-gray-500">Avg Total</div>
            </div>
        </div>

        <div class="mt-4 text-sm text-gray-500">
            <span class="font-medium">Last sync:</span>
            {{ $trackedAccount->last_sync_finished_at ? $trackedAccount->last_sync_finished_at->diffForHumans() : 'Never' }}
            @if($trackedAccount->last_sync_status === 'error' && $trackedAccount->last_sync_error)
                — <span class="text-red-600">{{ $trackedAccount->last_sync_error }}</span>
            @elseif($trackedAccount->last_sync_status)
                — {{ ucfirst($trackedAccount->last_sync_status) }}
            @endif
        </div>
    </div>

    {{-- Failed Posts Section --}}
    <div class="mb-6">
        <div class="flex items-center space-x-2 mb-3">
            <h2 class="text-lg font-semibold {{ $failedStatuses->isNotEmpty() ? 'text-red-700' : 'text-gray-700' }}">Failed Posts</h2>
            @if($failedStatuses->isNotEmpty())
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $failedStatuses->count() }}</span>
            @endif
        </div>
        @if($failedStatuses->isEmpty())
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-700">
                ✓ No failed posts — all tracked statuses are reachable.
            </div>
        @else
            <p class="text-sm text-gray-500 mb-3">These posts returned a "not found" error. They may have been deleted or made private.</p>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-red-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Post</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Engagement</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($failedStatuses as $failed)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-2 mb-1">
                                        @if($failed->is_boost)<span class="bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded text-xs">Boost</span>@endif
                                        @if($failed->is_reply)<span class="bg-yellow-100 text-yellow-800 px-1.5 py-0.5 rounded text-xs">Reply</span>@endif
                                        @if($failed->is_quote)<span class="bg-violet-100 text-violet-800 px-1.5 py-0.5 rounded text-xs">Quote</span>@endif
                                    </div>
                                    <a href="{{ route('statuses.show', $failed) }}" class="text-sm text-gray-700 hover:text-brand-dark line-clamp-1">
                                        {{ Str::limit(strip_tags($failed->content_html), 70) ?: '(no text content)' }}
                                    </a>
                                    @if($failed->status_url)
                                        <a href="{{ $failed->status_url }}" target="_blank" rel="noopener" class="text-xs text-brand-pink hover:text-brand-deep">View on instance ↗</a>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $failed->failed_at?->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($failed->summary)
                                        <div class="text-xs flex gap-2">
                                            <span>⭐ {{ $failed->summary->latest_favourites_count }}</span>
                                            <span>🔁 {{ $failed->summary->latest_boosts_count }}</span>
                                            <span>💬 {{ $failed->summary->latest_replies_count }}</span>
                                            <span>❝ {{ $failed->summary->latest_quotes_count }}</span>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <form action="{{ route('statuses.retry', $failed) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-green-100 text-green-800 hover:bg-green-200">
                                            Retry
                                        </button>
                                    </form>
                                    <form action="{{ route('statuses.archive', $failed) }}" method="POST" class="inline ml-1">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">
                                            Archive
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Top 5 Statuses by Engagement --}}
    @if($topStatuses->isNotEmpty())
        <div class="mb-6">
            <h2 class="text-lg font-semibold mb-3">Top Statuses by Engagement</h2>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Engagement</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($topStatuses as $top)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <a href="{{ route('statuses.show', $top) }}" class="text-sm text-brand-dark hover:text-brand-deep line-clamp-1">
                                        {{ Str::limit(strip_tags($top->content_html), 80) ?: '(no text content)' }}
                                    </a>
                                    <div class="text-xs text-gray-400 mt-1">{{ $top->created_at_remote?->diffForHumans() }}</div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="text-sm font-semibold text-gray-900">{{ number_format($top->summary->latestTotalEngagement()) }}</div>
                                    <div class="text-xs text-gray-400 flex gap-2 justify-end">
                                        <span>⭐ {{ $top->summary->latest_favourites_count }}</span>
                                        <span>🔁 {{ $top->summary->latest_boosts_count }}</span>
                                        <span>💬 {{ $top->summary->latest_replies_count }}</span>
                                        <span>❝ {{ $top->summary->latest_quotes_count }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Followers over time chart --}}
    @if($followerChartData->count() > 1)
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">Followers Over Time</h2>
            <div style="position: relative; height: 250px;">
                <canvas id="followersChart"></canvas>
            </div>
        </div>
    @endif

    {{-- Status type filters --}}
    <div id="statuses" class="flex flex-col md:flex-row items-start md:items-center justify-between mb-4 gap-3">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold">Statuses</h2>
            @if($accountStats['archived'] > 0)
                <a href="{{ route('tracked-accounts.archive', $trackedAccount) }}" class="text-sm text-brand-dark hover:text-brand-deep">
                    View Archive ({{ number_format($accountStats['archived']) }})
                </a>
            @endif
        </div>
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
            {{-- Time range selector --}}
            <div class="flex items-center space-x-1">
                @php
                    $ranges = [
                        'all' => 'All Time',
                        '2h' => '2h',
                        '24h' => '24h',
                        '7d' => '7d',
                        '30d' => '30d',
                    ];
                @endphp
                @foreach($ranges as $key => $label)
                    <a href="{{ route('tracked-accounts.show', ['trackedAccount' => $trackedAccount, 'filter' => $filter, 'range' => $key]) }}#statuses"
                       class="px-2 py-1 rounded text-xs {{ $range === $key ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            {{-- Type filters --}}
            <div class="flex items-center space-x-1">
                @php
                    $filters = [
                        'all' => 'All',
                        'originals' => 'Originals',
                        'quotes' => 'Quotes',
                        'replies' => 'Replies',
                        'boosts' => 'Boosts',
                        'media' => 'Media',
                    ];
                @endphp
                @foreach($filters as $key => $label)
                    <a href="{{ route('tracked-accounts.show', ['trackedAccount' => $trackedAccount, 'filter' => $key, 'range' => $range]) }}#statuses"
                       class="px-3 py-1 rounded-full text-sm {{ $filter === $key ? 'bg-brand-dark text-white' : 'bg-white text-gray-600 hover:bg-gray-100' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @if($statuses->isEmpty())
        <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            @if($filter !== 'all')
                No {{ strtolower($filters[$filter] ?? 'matching') }} statuses found.
                <a href="{{ route('tracked-accounts.show', $trackedAccount) }}" class="text-brand-dark hover:text-brand-deep ml-1">Show all</a>
            @else
                No statuses collected yet. The first sync will fetch recent posts.
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($statuses as $status)
                <a href="{{ route('statuses.show', $status) }}" class="flex flex-col bg-white rounded-lg shadow p-4 hover:ring-2 hover:ring-brand-pink/40 transition">
                    {{-- Badges + date + state --}}
                    <div class="flex flex-wrap items-center gap-1 text-xs text-gray-500 mb-2">
                        @if($status->is_boost)
                            <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Boost</span>
                            @if($status->boost_data_json)
                                <span class="text-gray-500 truncate max-w-[120px]">
                                    of <span class="font-medium text-gray-700">{{ $status->boost_data_json['author_display_name'] ?? $status->boost_data_json['author_acct'] ?? 'unknown' }}</span>
                                </span>
                            @endif
                        @endif
                        @if($status->is_reply)
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">Reply</span>
                        @endif
                        @if($status->is_quote)
                            <span class="bg-violet-100 text-violet-800 px-2 py-0.5 rounded">Quote</span>
                        @endif
                        @if($status->has_media)
                            <span class="bg-purple-100 text-purple-800 px-2 py-0.5 rounded">Media</span>
                        @endif
                        @if($status->has_poll)
                            <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded">Poll</span>
                        @endif
                        <span class="ml-auto">{{ $status->created_at_remote?->diffForHumans() ?? 'Unknown date' }}</span>
                        <span class="{{ $status->tracking_state === 'active' ? 'text-green-600' : 'text-gray-400' }}">
                            {{ ucfirst($status->tracking_state) }}
                        </span>
                    </div>

                    {{-- Post text --}}
                    <div class="text-sm text-gray-800 line-clamp-2 flex-1">
                        {{ html_entity_decode(strip_tags($status->content_html)) }}
                    </div>

                    {{-- Media thumbnails --}}
                    @if($status->has_media && $status->media_attachments_json)
                        <div class="mt-2 flex space-x-1 overflow-hidden">
                            @foreach(array_slice($status->media_attachments_json, 0, 3) as $media)
                                @if(($media['type'] ?? '') === 'image')
                                    <img src="{{ $media['preview_url'] ?? $media['url'] ?? '' }}" alt="{{ $media['description'] ?? '' }}" class="h-16 w-auto rounded object-cover flex-shrink-0" loading="lazy">
                                @elseif(($media['type'] ?? '') === 'video' || ($media['type'] ?? '') === 'gifv')
                                    <div class="h-16 w-24 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs flex-shrink-0">▶ Video</div>
                                @elseif(($media['type'] ?? '') === 'audio')
                                    <div class="h-16 w-24 rounded bg-gray-100 flex items-center justify-center text-gray-400 text-xs flex-shrink-0">♫ Audio</div>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    {{-- Engagement stats --}}
                    <div class="mt-3 pt-2 border-t border-gray-100 flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                        <span>⭐ {{ $status->summary?->latest_favourites_count ?? 0 }}</span>
                        <span>🔁 {{ $status->summary?->latest_boosts_count ?? 0 }}</span>
                        <span>💬 {{ $status->summary?->latest_replies_count ?? 0 }}</span>
                        <span>❝ {{ $status->summary?->latest_quotes_count ?? 0 }}</span>
                        @if($status->summary)
                            <span class="font-medium text-gray-700">{{ $status->summary->latestTotalEngagement() }} total</span>
                            @if($status->summary->snapshot_count > 1)
                                <span class="text-gray-400">{{ $status->summary->snapshot_count }} snaps</span>
                            @endif
                            @if($status->summary->peak_total_engagement > 0 && $status->summary->engagement_after_1h)
                                <span class="ml-auto {{ $status->summary->latestTotalEngagement() > $status->summary->engagement_after_1h ? 'text-green-600' : 'text-gray-400' }}">
                                    1h: {{ $status->summary->engagement_after_1h }}
                                    @if($status->summary->engagement_after_24h)
                                        → 24h: {{ $status->summary->engagement_after_24h }}
                                    @endif
                                </span>
                            @endif
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
@if($followerChartData->count() > 1)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const data = @json($followerChartData);

    new Chart(document.getElementById('followersChart'), {
        type: 'line',
        data: {
            labels: data.map(d => d.date),
            datasets: [
                {
                    label: 'Followers',
                    data: data.map(d => d.followers),
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    yAxisID: 'y',
                },
                {
                    label: 'Following',
                    data: data.map(d => d.following),
                    borderColor: 'rgb(156, 163, 175)',
                    backgroundColor: 'rgba(156, 163, 175, 0.1)',
                    tension: 0,
                    pointRadius: 3,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { title: { display: true, text: 'Date' } },
                y: {
                    title: { display: true, text: 'Followers' },
                    position: 'left',
                },
                y1: {
                    title: { display: true, text: 'Following' },
                    position: 'right',
                    grid: { drawOnChartArea: false },
                }
            }
        }
    });
});
</script>
@endif
@endpush
