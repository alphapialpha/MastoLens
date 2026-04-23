<?php

namespace Database\Factories;

use App\Models\StatusMetricSnapshot;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusMetricSnapshotFactory extends Factory
{
    protected $model = StatusMetricSnapshot::class;

    public function definition(): array
    {
        return [
            'status_id' => Status::factory(),
            'captured_at' => now(),
            'snapshot_target_age_minutes' => 0,
            'actual_age_minutes' => 0,
            'favourites_count' => fake()->numberBetween(0, 100),
            'boosts_count' => fake()->numberBetween(0, 50),
            'replies_count' => fake()->numberBetween(0, 30),
            'quotes_count' => 0,
        ];
    }
}
