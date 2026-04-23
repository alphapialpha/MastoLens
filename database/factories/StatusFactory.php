<?php

namespace Database\Factories;

use App\Models\Status;
use App\Models\TrackedAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusFactory extends Factory
{
    protected $model = Status::class;

    public function definition(): array
    {
        $domain = fake()->domainName();

        return [
            'tracked_account_id' => TrackedAccount::factory(),
            'instance_domain' => $domain,
            'remote_status_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'status_url' => "https://{$domain}/@user/" . fake()->unique()->numberBetween(100000, 999999),
            'uri' => "https://{$domain}/users/user/statuses/" . fake()->unique()->numberBetween(100000, 999999),
            'created_at_remote' => now()->subHours(fake()->numberBetween(1, 48)),
            'fetched_first_at' => now(),
            'fetched_last_at' => now(),
            'content_html' => '<p>' . fake()->sentence() . '</p>',
            'content_text' => fake()->sentence(),
            'visibility' => 'public',
            'is_sensitive' => false,
            'is_reply' => false,
            'is_boost' => false,
            'has_media' => false,
            'has_poll' => false,
            'has_card' => false,
            'media_count' => 0,
            'tracking_state' => 'active',
            'next_snapshot_due_at' => now(),
        ];
    }

    public function reply(): static
    {
        return $this->state(fn () => [
            'is_reply' => true,
            'in_reply_to_remote_status_id' => (string) fake()->numberBetween(100000, 999999),
        ]);
    }

    public function boost(): static
    {
        return $this->state(fn () => [
            'is_boost' => true,
            'boost_of_remote_status_id' => (string) fake()->numberBetween(100000, 999999),
        ]);
    }

    public function withMedia(): static
    {
        return $this->state(fn () => [
            'has_media' => true,
            'media_count' => fake()->numberBetween(1, 4),
            'media_attachments_json' => [
                ['id' => '1', 'type' => 'image', 'url' => 'https://example.com/media/image.jpg', 'preview_url' => 'https://example.com/media/image_preview.jpg', 'description' => 'Test image'],
            ],
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'tracking_state' => 'archived',
            'archived_at' => now(),
            'next_snapshot_due_at' => null,
        ]);
    }

    public function old(int $days = 31): static
    {
        return $this->state(fn () => [
            'created_at_remote' => now()->subDays($days),
        ]);
    }
}
