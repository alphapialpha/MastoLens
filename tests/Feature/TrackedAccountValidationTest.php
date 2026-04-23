<?php

namespace Tests\Feature;

use App\Models\TrackedAccount;
use App\Models\User;
use App\Services\MastodonApiService;
use App\Services\WebFingerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackedAccountValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_handle_is_required(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('tracked-accounts.store'), ['handle' => '']);

        $response->assertSessionHasErrors('handle');
    }

    public function test_handle_must_be_valid_format(): void
    {
        $invalidHandles = [
            'justausername',
            '@justausername',
            'user@',
            '@user@',
            'user@invalid',
            '@user@x',
            'user with spaces@instance.tld',
        ];

        foreach ($invalidHandles as $handle) {
            $response = $this->actingAs($this->user)
                ->post(route('tracked-accounts.store'), ['handle' => $handle]);

            $response->assertSessionHasErrors('handle', "Handle '{$handle}' should be invalid");
        }
    }

    public function test_handle_format_accepts_valid_handles(): void
    {
        $this->mock(WebFingerService::class, function ($mock) {
            $mock->shouldReceive('parseHandle')->andReturn([
                'username' => 'user',
                'instance_domain' => 'mastodon.social',
                'acct_normalized' => 'user@mastodon.social',
            ]);
            $mock->shouldReceive('resolve')->andReturn([
                'profile_url' => 'https://mastodon.social/@user',
            ]);
        });

        $this->mock(MastodonApiService::class, function ($mock) {
            $mock->shouldReceive('lookupAccount')->andReturn([
                'id' => '99999',
                'url' => 'https://mastodon.social/@user',
                'display_name' => 'Test User',
                'avatar_static' => null,
                'note' => '',
                'followers_count' => 100,
                'following_count' => 50,
                'statuses_count' => 200,
            ]);
        });

        $response = $this->actingAs($this->user)
            ->post(route('tracked-accounts.store'), ['handle' => 'user@mastodon.social']);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors('handle');
    }

    public function test_duplicate_account_for_same_user_rejected(): void
    {
        TrackedAccount::factory()->create([
            'user_id' => $this->user->id,
            'acct_normalized' => 'gargron@mastodon.social',
        ]);

        $this->mock(WebFingerService::class, function ($mock) {
            $mock->shouldReceive('parseHandle')->andReturn([
                'username' => 'Gargron',
                'instance_domain' => 'mastodon.social',
                'acct_normalized' => 'gargron@mastodon.social',
            ]);
        });

        $response = $this->actingAs($this->user)
            ->post(route('tracked-accounts.store'), ['handle' => 'Gargron@mastodon.social']);

        $response->assertSessionHasErrors('handle');
    }

    public function test_same_account_allowed_for_different_users(): void
    {
        $user2 = User::factory()->create();

        TrackedAccount::factory()->create([
            'user_id' => $this->user->id,
            'acct_normalized' => 'gargron@mastodon.social',
        ]);

        // User2 should be able to add the same account — mock full resolution
        $this->mock(WebFingerService::class, function ($mock) {
            $mock->shouldReceive('parseHandle')->andReturn([
                'username' => 'Gargron',
                'instance_domain' => 'mastodon.social',
                'acct_normalized' => 'gargron@mastodon.social',
            ]);
            $mock->shouldReceive('resolve')->andReturn([
                'profile_url' => 'https://mastodon.social/@Gargron',
            ]);
        });

        $this->mock(MastodonApiService::class, function ($mock) {
            $mock->shouldReceive('lookupAccount')->andReturn([
                'id' => '1',
                'url' => 'https://mastodon.social/@Gargron',
                'display_name' => 'Eugen Rochko',
                'avatar_static' => null,
                'note' => '',
                'followers_count' => 300000,
                'following_count' => 500,
                'statuses_count' => 70000,
            ]);
        });

        $response = $this->actingAs($user2)
            ->post(route('tracked-accounts.store'), ['handle' => 'Gargron@mastodon.social']);

        $response->assertRedirect();
        $this->assertDatabaseCount('tracked_accounts', 2);
    }

    public function test_unauthenticated_user_cannot_store(): void
    {
        $response = $this->post(route('tracked-accounts.store'), ['handle' => 'user@instance.tld']);

        $response->assertRedirect(route('login'));
    }
}
