<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusSummary extends Model
{
    use HasFactory;
    protected $fillable = [
        'status_id',
        'latest_snapshot_at',
        'latest_favourites_count',
        'latest_boosts_count',
        'latest_replies_count',
        'latest_quotes_count',
        'snapshot_count',
        'first_seen_at',
        'last_seen_at',
        'archived_at',
        'peak_total_engagement',
        'engagement_after_1h',
        'engagement_after_24h',
        'engagement_after_7d',
    ];

    protected function casts(): array
    {
        return [
            'latest_snapshot_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function latestTotalEngagement(): int
    {
        return $this->latest_favourites_count + $this->latest_boosts_count
            + $this->latest_replies_count + $this->latest_quotes_count;
    }
}
