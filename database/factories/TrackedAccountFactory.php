<?php

namespace Database\Factories;

use App\Models\TrackedAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackedAccountFactory extends Factory
{
    protected $model = TrackedAccount::class;

    public function definition(): array
    {
        $username = fake()->userName();
        $domain = fake()->domainName();

        return [
            'user_id' => User::factory(),
            'acct_input' => "{$username}@{$domain}",
            'username' => $username,
            'instance_domain' => $domain,
            'acct_normalized' => "{$username}@{$domain}",
            'remote_account_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'account_url' => "https://{$domain}/@{$username}",
            'display_name' => fake()->name(),
            'avatar_url' => null,
            'note_html' => null,
            'followers_count_latest' => fake()->numberBetween(0, 100000),
            'following_count_latest' => fake()->numberBetween(0, 5000),
            'statuses_count_latest' => fake()->numberBetween(0, 50000),
            'is_active' => true,
            'last_resolved_at' => now(),
            'last_sync_started_at' => null,
            'last_sync_finished_at' => null,
            'last_sync_status' => null,
            'last_sync_error' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
