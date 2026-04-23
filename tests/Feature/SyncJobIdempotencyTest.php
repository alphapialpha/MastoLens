<?php

namespace Tests\Feature;

use App\Jobs\SyncTrackedAccountStatusesJob;
use App\Models\AccountMetricSnapshot;
use App\Models\Status;
use App\Models\TrackedAccount;
use App\Services\MastodonApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncJobIdempotencyTest extends TestCase
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
            'followers_count_latest' => 1000,
        ]);
    }

    private function mockApi(array $statuses = []): void
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

    private function makeStatus(string $id): array
    {
        return [
            'id' => $id,
            'created_at' => now()->subMinutes(5)->toIso8601String(),
            'content' => '<p>Test</p>',
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
        ];
    }

    public function test_running_sync_twice_does_not_duplicate_statuses(): void
    {
        $this->mockApi([$this->makeStatus('100001'), $this->makeStatus('100002')]);

        $job = new SyncTrackedAccountStatusesJob($this->account);
        $job->handle(app(MastodonApiService::class));
        $job->handle(app(MastodonApiService::class));

        $this->assertDatabaseCount('statuses', 2);
    }

    public function test_sync_log_created_each_run(): void
    {
        $this->mockApi([$this->makeStatus('100001')]);

        $job = new SyncTrackedAccountStatusesJob($this->account);
        $job->handle(app(MastodonApiService::class));
        $job->handle(app(MastodonApiService::class));

        $this->assertEquals(2, $this->account->syncLogs()->count());
    }

    public function test_daily_follower_snapshot_captured_once_per_day(): void
    {
        $this->mockApi([]);

        $job = new SyncTrackedAccountStatusesJob($this->account);

        // Run three times on the same day
        $job->handle(app(MastodonApiService::class));
        $job->handle(app(MastodonApiService::class));
        $job->handle(app(MastodonApiService::class));

        // Only 1 snapshot for today
        $this->assertEquals(1, AccountMetricSnapshot::where('tracked_account_id', $this->account->id)->count());
    }

    public function test_account_metadata_updated_on_sync(): void
    {
        $this->mock(MastodonApiService::class, function ($mock) {
            $mock->shouldReceive('getAccountStatuses')->andReturn([]);
            $mock->shouldReceive('lookupAccount')->andReturn([
                'id' => '12345',
                'display_name' => 'Updated Name',
                'followers_count' => 2000,
                'following_count' => 200,
                'statuses_count' => 600,
            ]);
        });

        (new SyncTrackedAccountStatusesJob($this->account))->handle(app(MastodonApiService::class));

        $account = $this->account->fresh();
        $this->assertEquals('Updated Name', $account->display_name);
        $this->assertEquals(2000, $account->followers_count_latest);
        $this->assertEquals('success', $account->last_sync_status);
    }
}
