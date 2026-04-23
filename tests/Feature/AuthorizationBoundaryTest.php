<?php

namespace Tests\Feature;

use App\Models\Status;
use App\Models\StatusSummary;
use App\Models\TrackedAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private TrackedAccount $account1;
    private TrackedAccount $account2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();

        $this->account1 = TrackedAccount::factory()->create(['user_id' => $this->user1->id]);
        $this->account2 = TrackedAccount::factory()->create(['user_id' => $this->user2->id]);
    }

    public function test_user_can_view_own_tracked_account(): void
    {
        $response = $this->actingAs($this->user1)
            ->get(route('tracked-accounts.show', $this->account1));

        $response->assertOk();
    }

    public function test_user_cannot_view_other_users_tracked_account(): void
    {
        $response = $this->actingAs($this->user1)
            ->get(route('tracked-accounts.show', $this->account2));

        $response->assertForbidden();
    }

    public function test_user_can_toggle_own_account(): void
    {
        $response = $this->actingAs($this->user1)
            ->patch(route('tracked-accounts.toggle', $this->account1));

        $response->assertRedirect();
        $this->assertFalse($this->account1->fresh()->is_active);
    }

    public function test_user_cannot_toggle_other_users_account(): void
    {
        $response = $this->actingAs($this->user1)
            ->patch(route('tracked-accounts.toggle', $this->account2));

        $response->assertForbidden();
    }

    public function test_user_can_delete_own_account(): void
    {
        $response = $this->actingAs($this->user1)
            ->delete(route('tracked-accounts.destroy', $this->account1));

        $response->assertRedirect();
        $this->assertDatabaseMissing('tracked_accounts', ['id' => $this->account1->id]);
    }

    public function test_user_cannot_delete_other_users_account(): void
    {
        $response = $this->actingAs($this->user1)
            ->delete(route('tracked-accounts.destroy', $this->account2));

        $response->assertForbidden();
    }

    public function test_user_can_view_own_status(): void
    {
        $status = Status::factory()->create(['tracked_account_id' => $this->account1->id]);

        $response = $this->actingAs($this->user1)
            ->get(route('statuses.show', $status));

        $response->assertOk();
    }

    public function test_user_cannot_view_other_users_status(): void
    {
        $status = Status::factory()->create(['tracked_account_id' => $this->account2->id]);

        $response = $this->actingAs($this->user1)
            ->get(route('statuses.show', $status));

        $response->assertForbidden();
    }

    public function test_dashboard_only_shows_own_accounts(): void
    {
        $response = $this->actingAs($this->user1)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($this->account1->acct_normalized);
        $response->assertDontSee($this->account2->acct_normalized);
    }

    public function test_tracked_accounts_index_only_shows_own(): void
    {
        $response = $this->actingAs($this->user1)->get(route('tracked-accounts.index'));

        $response->assertOk();
        $response->assertSee($this->account1->acct_normalized);
        $response->assertDontSee($this->account2->acct_normalized);
    }

    public function test_unauthenticated_user_redirected_from_dashboard(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_redirected_from_account_show(): void
    {
        $response = $this->get(route('tracked-accounts.show', $this->account1));
        $response->assertRedirect(route('login'));
    }
}
