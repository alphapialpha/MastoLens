<?php

namespace Tests\Feature;

use App\Jobs\CaptureStatusSnapshotJob;
use App\Models\Status;
use App\Models\StatusMetricSnapshot;
use App\Models\TrackedAccount;
use App\Services\MastodonApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();

        $account = TrackedAccount::factory()->create();
        $this->status = Status::factory()->create([
            'tracked_account_id' => $account->id,
            'created_at_remote' => now()->subMinutes(20), // ~20 min old, should target 15m or 30m
            'tracking_state' => 'active',
        ]);
    }

    private function mockStatusApi(int $favs = 10, int $boosts = 5, int $replies = 2): void
    {
        $this->mock(MastodonApiService::class, function ($mock) use ($favs, $boosts, $replies) {
            $mock->shouldReceive('getStatus')->andReturn([
                'status' => 'ok',
                'data' => [
                    'id' => $this->status->remote_status_id,
                    'favourites_count' => $favs,
                    'reblogs_count' => $boosts,
                    'replies_count' => $replies,
                ],
                'http_code' => 200,
            ]);
        });
    }

    public function test_snapshot_not_duplicated_for_same_target_age(): void
    {
        $this->mockStatusApi();

        $job = new CaptureStatusSnapshotJob($this->status);

        // Run twice
        $job->handle(app(MastodonApiService::class));
        $job->handle(app(MastodonApiService::class));

        // Should only have 1 snapshot for the target age
        $snapshots = $this->status->metricSnapshots()->get();
        $targetAges = $snapshots->pluck('snapshot_target_age_minutes');

        // Each target age should appear at most once
        $this->assertEquals($targetAges->count(), $targetAges->unique()->count());
    }

    public function test_initial_0m_snapshot_created_once(): void
    {
        // Pre-create the initial snapshot like SyncJob does
        StatusMetricSnapshot::factory()->create([
            'status_id' => $this->status->id,
            'snapshot_target_age_minutes' => 0,
        ]);

        $this->mockStatusApi();

        (new CaptureStatusSnapshotJob($this->status))->handle(app(MastodonApiService::class));

        // 0m should still be exactly 1
        $zeroSnapshots = $this->status->metricSnapshots()
            ->where('snapshot_target_age_minutes', 0)
            ->count();

        $this->assertEquals(1, $zeroSnapshots);
    }

    public function test_archived_status_skips_snapshot(): void
    {
        $this->status->update(['tracking_state' => 'archived']);

        $this->mockStatusApi();

        (new CaptureStatusSnapshotJob($this->status))->handle(app(MastodonApiService::class));

        $this->assertDatabaseCount('status_metric_snapshots', 0);
    }

    public function test_summary_updates_with_latest_counts(): void
    {
        $this->mockStatusApi(favs: 25, boosts: 12, replies: 8);

        (new CaptureStatusSnapshotJob($this->status))->handle(app(MastodonApiService::class));

        $summary = $this->status->fresh()->summary;
        $this->assertNotNull($summary);
        $this->assertEquals(25, $summary->latest_favourites_count);
        $this->assertEquals(12, $summary->latest_boosts_count);
        $this->assertEquals(8, $summary->latest_replies_count);
    }

    public function test_peak_engagement_tracks_maximum(): void
    {
        // Create initial summary with low engagement
        $this->status->summary()->create([
            'latest_snapshot_at' => now(),
            'latest_favourites_count' => 5,
            'latest_boosts_count' => 2,
            'latest_replies_count' => 1,
            'latest_quotes_count' => 0,
            'snapshot_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'peak_total_engagement' => 8,
        ]);

        // Return higher engagement
        $this->mockStatusApi(favs: 50, boosts: 20, replies: 10);

        (new CaptureStatusSnapshotJob($this->status))->handle(app(MastodonApiService::class));

        $summary = $this->status->fresh()->summary;
        $this->assertEquals(80, $summary->peak_total_engagement);
    }
}
