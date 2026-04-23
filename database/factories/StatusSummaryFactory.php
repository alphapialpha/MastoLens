<?php

namespace Database\Factories;

use App\Models\StatusSummary;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusSummaryFactory extends Factory
{
    protected $model = StatusSummary::class;

    public function definition(): array
    {
        return [
            'status_id' => Status::factory(),
            'latest_snapshot_at' => now(),
            'latest_favourites_count' => fake()->numberBetween(0, 100),
            'latest_boosts_count' => fake()->numberBetween(0, 50),
            'latest_replies_count' => fake()->numberBetween(0, 30),
            'latest_quotes_count' => 0,
            'snapshot_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'peak_total_engagement' => fake()->numberBetween(0, 200),
        ];
    }
}
