<?php

namespace Tests\Feature;

use App\Jobs\SyncTrackedAccountStatusesJob;
use App\Models\Status;
use App\Models\TrackedAccount;
use App\Models\User;
use App\Services\MastodonApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusIngestionTest extends TestCase
{
    use RefreshDatabase;

    private TrackedAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = TrackedAccount::factory()->create([
            'instance_domain' => 'mastodon.social',
            'remote_account_id' => '12345',
            'username' => 'testuser',
        ]);
    }

    private function mockMastodonApi(array $statuses): void
    {
        $this->mock(MastodonApiService::class, function ($mock) use ($statuses) {
            $mock->shouldReceive('getAccountStatuses')->andReturn($statuses);
            $mock->shouldReceive('lookupAccount')->andReturn([
                'id' => '12345',
                'display_name' => 'Test User',
                'followers_count' => 1000,
                'following_count' => 100,
                'statuses_count' => 500,
            ]);
        });
    }

    private function makeStatusPayload(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'created_at' => now()->subMinutes(5)->toIso8601String(),
            'content' => '<p>Test post</p>',
            'url' => "https://mastodon.social/@testuser/{$id}",
            'uri' => "https://mastodon.social/users/testuser/statuses/{$id}",
            'visibility' => 'public',
            'sensitive' => false,
            'spoiler_text' => '',
            'language' => 'en',
            'in_reply_to_id' => null,
            'in_reply_to_account_id' => null,
            'reblog' => null,
            'media_attachments' => [],
            'mentions' => [],
            'tags' => [],
            'emojis' => [],
            'poll' => null,
            'card' => null,
            'favourites_count' => 10,
            'reblogs_count' => 5,
            'replies_count' => 2,
        ], $overrides);
    }

    public function test_new_statuses_are_ingested(): void
    {
        $this->mockMastodonApi([
            $this->makeStatusPayload('100001'),
            $this->makeStatusPayload('100002'),
        ]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $this->assertDatabaseCount('statuses', 2);
        $this->assertDatabaseHas('statuses', [
            'tracked_account_id' => $this->account->id,
            'remote_status_id' => '100001',
        ]);
        $this->assertDatabaseHas('statuses', [
            'tracked_account_id' => $this->account->id,
            'remote_status_id' => '100002',
        ]);
    }

    public function test_duplicate_statuses_are_not_created(): void
    {
        $statusPayload = $this->makeStatusPayload('100001');
        $this->mockMastodonApi([$statusPayload]);

        $job = new SyncTrackedAccountStatusesJob($this->account);

        // Run twice
        $job->handle(app(MastodonApiService::class));
        $job->handle(app(MastodonApiService::class));

        // Should still be exactly 1 status
        $this->assertDatabaseCount('statuses', 1);
    }

    public function test_existing_status_gets_fetched_last_at_updated(): void
    {
        // Pre-create a status
        $status = Status::factory()->create([
            'tracked_account_id' => $this->account->id,
            'remote_status_id' => '100001',
            'instance_domain' => 'mastodon.social',
            'fetched_last_at' => now()->subHour(),
        ]);

        $originalFetchedAt = $status->fetched_last_at;

        $this->mockMastodonApi([$this->makeStatusPayload('100001')]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $this->assertDatabaseCount('statuses', 1);
        $this->assertTrue($status->fresh()->fetched_last_at->gt($originalFetchedAt));
    }

    public function test_reply_status_is_classified_correctly(): void
    {
        $this->mockMastodonApi([
            $this->makeStatusPayload('100001', [
                'in_reply_to_id' => '999',
                'in_reply_to_account_id' => '888',
            ]),
        ]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $status = Status::where('remote_status_id', '100001')->first();
        $this->assertTrue($status->is_reply);
        $this->assertFalse($status->is_boost);
    }

    public function test_boost_status_is_classified_correctly(): void
    {
        $this->mockMastodonApi([
            $this->makeStatusPayload('100001', [
                'reblog' => ['id' => '777', 'content' => '<p>Boosted</p>'],
            ]),
        ]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $status = Status::where('remote_status_id', '100001')->first();
        $this->assertTrue($status->is_boost);
        $this->assertEquals('777', $status->boost_of_remote_status_id);
    }

    public function test_media_status_is_classified_correctly(): void
    {
        $this->mockMastodonApi([
            $this->makeStatusPayload('100001', [
                'media_attachments' => [
                    ['id' => '1', 'type' => 'image', 'url' => 'https://example.com/img.jpg'],
                ],
            ]),
        ]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $status = Status::where('remote_status_id', '100001')->first();
        $this->assertTrue($status->has_media);
        $this->assertEquals(1, $status->media_count);
    }

    public function test_initial_snapshot_is_created_on_ingestion(): void
    {
        $this->mockMastodonApi([$this->makeStatusPayload('100001')]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $status = Status::where('remote_status_id', '100001')->first();
        $this->assertEquals(1, $status->metricSnapshots()->count());
        $this->assertEquals(0, $status->metricSnapshots()->first()->snapshot_target_age_minutes);
    }

    public function test_summary_is_created_on_ingestion(): void
    {
        $this->mockMastodonApi([
            $this->makeStatusPayload('100001', [
                'favourites_count' => 15,
                'reblogs_count' => 8,
                'replies_count' => 3,
            ]),
        ]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $status = Status::where('remote_status_id', '100001')->first();
        $this->assertNotNull($status->summary);
        $this->assertEquals(15, $status->summary->latest_favourites_count);
        $this->assertEquals(8, $status->summary->latest_boosts_count);
        $this->assertEquals(3, $status->summary->latest_replies_count);
    }

    public function test_old_status_is_immediately_archived(): void
    {
        $this->mockMastodonApi([
            $this->makeStatusPayload('100001', [
                'created_at' => now()->subDays(35)->toIso8601String(),
            ]),
        ]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $status = Status::where('remote_status_id', '100001')->first();
        $this->assertEquals('archived', $status->tracking_state);
        $this->assertNotNull($status->archived_at);
        $this->assertNull($status->next_snapshot_due_at);
    }

    public function test_inactive_account_sync_is_skipped(): void
    {
        $this->account->update(['is_active' => false]);

        $this->mockMastodonApi([$this->makeStatusPayload('100001')]);

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $this->assertDatabaseCount('statuses', 0);
    }
}
