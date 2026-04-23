<?php

namespace Tests\Feature;

use App\Jobs\ArchiveExpiredStatusesJob;
use App\Models\Status;
use App\Models\StatusSummary;
use App\Models\TrackedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTransitionTest extends TestCase
{
    use RefreshDatabase;

    private TrackedAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = TrackedAccount::factory()->create();
    }

    public function test_statuses_older_than_30_days_are_archived(): void
    {
        $old = Status::factory()->create([
            'tracked_account_id' => $this->account->id,
            'created_at_remote' => now()->subDays(31),
            'tracking_state' => 'active',
        ]);

        (new ArchiveExpiredStatusesJob())->handle();

        $this->assertEquals('archived', $old->fresh()->tracking_state);
        $this->assertNotNull($old->fresh()->archived_at);
        $this->assertNull($old->fresh()->next_snapshot_due_at);
    }

    public function test_statuses_under_30_days_are_not_archived(): void
    {
        $recent = Status::factory()->create([
            'tracked_account_id' => $this->account->id,
            'created_at_remote' => now()->subDays(20),
            'tracking_state' => 'active',
        ]);

        (new ArchiveExpiredStatusesJob())->handle();

        $this->assertEquals('active', $recent->fresh()->tracking_state);
    }

    public function test_already_archived_statuses_not_touched(): void
    {
        $archived = Status::factory()->archived()->create([
            'tracked_account_id' => $this->account->id,
            'created_at_remote' => now()->subDays(60),
            'archived_at' => now()->subDays(30),
        ]);

        $originalArchivedAt = $archived->archived_at;

        (new ArchiveExpiredStatusesJob())->handle();

        // Should not be re-archived
        $this->assertEquals($originalArchivedAt->toDateTimeString(), $archived->fresh()->archived_at->toDateTimeString());
    }

    public function test_summary_archived_at_is_updated(): void
    {
        $status = Status::factory()->create([
            'tracked_account_id' => $this->account->id,
            'created_at_remote' => now()->subDays(31),
            'tracking_state' => 'active',
        ]);

        StatusSummary::factory()->create([
            'status_id' => $status->id,
            'archived_at' => null,
        ]);

        (new ArchiveExpiredStatusesJob())->handle();

        $this->assertNotNull($status->summary->fresh()->archived_at);
    }

    public function test_archive_respects_batch_limit(): void
    {
        // Create 510 old statuses — job limit is 500
        Status::factory()->count(510)->create([
            'tracked_account_id' => $this->account->id,
            'created_at_remote' => now()->subDays(35),
            'tracking_state' => 'active',
        ]);

        (new ArchiveExpiredStatusesJob())->handle();

        $remaining = Status::where('tracking_state', 'active')->count();
        $this->assertEquals(10, $remaining);
    }
}
