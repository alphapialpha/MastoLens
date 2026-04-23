<?php

namespace App\Jobs;

use App\Models\Status;
use App\Services\MastodonApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CaptureStatusSnapshotJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 60;

    /**
     * Target snapshot ages in minutes per spec §8.2.
     */
    public const SNAPSHOT_TARGETS = [
        0,          // 0m
        15,         // 15m
        30,         // 30m
        60,         // 1h
        120,        // 2h
        240,        // 4h
        480,        // 8h
        720,        // 12h
        1440,       // 24h
        2880,       // 2d
        4320,       // 3d
        10080,      // 7d
        20160,      // 14d
        43200,      // 30d
    ];

    public function __construct(
        public Status $status,
    ) {}

    public function uniqueId(): string
    {
        return 'snapshot-status-' . $this->status->id;
    }

    public function handle(MastodonApiService $mastodon): void
    {
        $status = $this->status;

        if ($status->tracking_state !== 'active') {
            return;
        }

        $result = $mastodon->getStatus(
            $status->instance_domain,
            $status->remote_status_id
        );

        if ($result['status'] === 'not_found') {
            // Post is confirmed deleted (404/410) — mark as failed
            $status->update([
                'tracking_state' => 'failed',
                'failed_at' => now(),
                'next_snapshot_due_at' => null,
            ]);
            return;
        }

        if ($result['status'] === 'error') {
            // Transient error (instance down, timeout, 500, etc.) — retry later
            $status->update(['next_snapshot_due_at' => now()->addMinutes(5)]);
            return;
        }

        $statusData = $result['data'];

        $ageMinutes = $status->created_at_remote
            ? max(0, (int) $status->created_at_remote->diffInMinutes(now()))
            : 0;

        $targetAge = $this->findTargetAge($ageMinutes);

        if ($targetAge === null) {
            // Past all targets — archive
            $this->archiveStatus($status);
            return;
        }

        // Check if this target was already captured (idempotency)
        $alreadyCaptured = $status->metricSnapshots()
            ->where('snapshot_target_age_minutes', $targetAge)
            ->exists();

        $favourites = $statusData['favourites_count'] ?? 0;
        $boosts = $statusData['reblogs_count'] ?? 0;
        $replies = $statusData['replies_count'] ?? 0;
        $quotes = 0;

        if (!$alreadyCaptured) {
            $status->metricSnapshots()->create([
                'captured_at' => now(),
                'snapshot_target_age_minutes' => $targetAge,
                'actual_age_minutes' => $ageMinutes,
                'favourites_count' => $favourites,
                'boosts_count' => $boosts,
                'replies_count' => $replies,
                'quotes_count' => $quotes,
            ]);
        }

        // Update summary
        $totalEngagement = $favourites + $boosts + $replies + $quotes;
        $snapshotCount = $status->metricSnapshots()->count();

        $summaryData = [
            'latest_snapshot_at' => now(),
            'latest_favourites_count' => $favourites,
            'latest_boosts_count' => $boosts,
            'latest_replies_count' => $replies,
            'latest_quotes_count' => $quotes,
            'snapshot_count' => $snapshotCount,
            'last_seen_at' => now(),
            'peak_total_engagement' => $totalEngagement,
        ];

        // Set milestone engagement fields
        if ($targetAge <= 60) {
            $summaryData['engagement_after_1h'] = $totalEngagement;
        }
        if ($targetAge <= 1440) {
            $summaryData['engagement_after_24h'] = $totalEngagement;
        }
        if ($targetAge <= 10080) {
            $summaryData['engagement_after_7d'] = $totalEngagement;
        }

        $summary = $status->summary()->first();
        if ($summary) {
            // Keep peak as the max
            $summaryData['peak_total_engagement'] = max($totalEngagement, $summary->peak_total_engagement);
            $summary->update($summaryData);
        } else {
            $summaryData['first_seen_at'] = now();
            $status->summary()->create($summaryData);
        }

        // Calculate next due snapshot
        $nextDue = $this->calculateNextSnapshotDue($status);

        if ($nextDue) {
            $status->update([
                'next_snapshot_due_at' => $nextDue,
                'fetched_last_at' => now(),
            ]);
        } else {
            $this->archiveStatus($status);
        }
    }

    private function findTargetAge(int $ageMinutes): ?int
    {
        // Find the closest target age that hasn't been significantly passed
        foreach (self::SNAPSHOT_TARGETS as $target) {
            if ($target === 0) {
                continue; // 0m is captured during discovery
            }
            // Allow some tolerance — capture if we're within 50% of the gap to next target
            if ($ageMinutes <= $target * 1.5) {
                return $target;
            }
        }

        return null;
    }

    private function calculateNextSnapshotDue(Status $status): ?Carbon
    {
        if (!$status->created_at_remote) {
            return null;
        }

        $captured = $status->metricSnapshots()
            ->pluck('snapshot_target_age_minutes')
            ->all();

        $ageMinutes = max(0, (int) $status->created_at_remote->diffInMinutes(now()));

        foreach (self::SNAPSHOT_TARGETS as $target) {
            if ($target === 0) {
                continue;
            }
            if (in_array($target, $captured, true)) {
                continue;
            }
            // Schedule if target is in the future, or past but still within
            // the 1.5x tolerance window used by findTargetAge()
            if ($target > $ageMinutes || $ageMinutes <= $target * 1.5) {
                $dueAt = $status->created_at_remote->copy()->addMinutes($target);
                return $dueAt->lt(now()) ? now() : $dueAt;
            }
        }

        return null;
    }

    private function archiveStatus(Status $status): void
    {
        $status->update([
            'tracking_state' => 'archived',
            'archived_at' => now(),
            'next_snapshot_due_at' => null,
        ]);

        $status->summary?->update(['archived_at' => now()]);
    }
}
