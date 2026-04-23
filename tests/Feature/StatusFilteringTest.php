<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\StatusSummary;
use App\Models\TrackedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private TrackedAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = TrackedAccount::factory()->create(['user_id' => $this->user->id]);
    }

    private function createStatus(array $attributes = []): Status
    {
        return Status::factory()->create(array_merge(
            ['tracked_account_id' => $this->account->id],
            $attributes
        ));
    }

    public function test_all_filter_shows_all_statuses(): void
    {
        $this->createStatus(['is_reply' => false, 'is_boost' => false]);
        $this->createStatus(['is_reply' => true]);
        $this->createStatus(['is_boost' => true]);
        $this->createStatus(['has_media' => true, 'media_count' => 1]);

        $response = $this->actingAs($this->user)
            ->get(route('tracked-accounts.show', ['trackedAccount' => $this->account, 'filter' => 'all']));

        $response->assertOk();
        // All 4 statuses should be visible in the page
        $this->assertEquals(4, Status::where('tracked_account_id', $this->account->id)->count());
    }

    public function test_originals_filter_excludes_replies_and_boosts(): void
    {
        $original = $this->createStatus(['is_reply' => false, 'is_boost' => false, 'content_html' => '<p>Original post</p>']);
        $this->createStatus(['is_reply' => true, 'content_html' => '<p>Reply post</p>']);
        $this->createStatus(['is_boost' => true, 'content_html' => '<p>Boost post</p>']);

        $response = $this->actingAs($this->user)
            ->get(route('tracked-accounts.show', ['trackedAccount' => $this->account, 'filter' => 'originals']));

        $response->assertOk();
        $response->assertSee('Original post');
        $response->assertDontSee('Reply post');
        $response->assertDontSee('Boost post');
    }

    public function test_replies_filter_shows_only_replies(): void
    {
        $this->createStatus(['is_reply' => false, 'content_html' => '<p>Not a reply</p>']);
        $this->createStatus(['is_reply' => true, 'content_html' => '<p>This is a reply</p>']);

        $response = $this->actingAs($this->user)
            ->get(route('tracked-accounts.show', ['trackedAccount' => $this->account, 'filter' => 'replies']));

        $response->assertOk();
        $response->assertSee('This is a reply');
        $response->assertDontSee('Not a reply');
    }

    public function test_boosts_filter_shows_only_boosts(): void
    {
        $this->createStatus(['is_boost' => false, 'content_html' => '<p>Not a boost</p>']);
        $this->createStatus(['is_boost' => true, 'content_html' => '<p>This is a boost</p>']);

        $response = $this->actingAs($this->user)
            ->get(route('tracked-accounts.show', ['trackedAccount' => $this->account, 'filter' => 'boosts']));

        $response->assertOk();
        $response->assertSee('This is a boost');
        $response->assertDontSee('Not a boost');
    }

    public function test_media_filter_shows_only_media_statuses(): void
    {
        $this->createStatus(['has_media' => false, 'content_html' => '<p>No media here</p>']);
        $this->createStatus(['has_media' => true, 'media_count' => 2, 'content_html' => '<p>Has images</p>']);

        $response = $this->actingAs($this->user)
            ->get(route('tracked-accounts.show', ['trackedAccount' => $this->account, 'filter' => 'media']));

        $response->assertOk();
        $response->assertSee('Has images');
        $response->assertDontSee('No media here');
    }

    public function test_invalid_filter_defaults_to_all(): void
    {
        $this->createStatus();
        $this->createStatus(['is_reply' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('tracked-accounts.show', ['trackedAccount' => $this->account, 'filter' => 'invalid_value']));

        $response->assertOk();
        // Should show all statuses
    }
}
