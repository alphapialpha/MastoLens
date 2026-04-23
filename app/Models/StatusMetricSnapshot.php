<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusMetricSnapshot extends Model
{
    use HasFactory;
    protected $fillable = [
        'status_id',
        'captured_at',
        'snapshot_target_age_minutes',
        'actual_age_minutes',
        'favourites_count',
        'boosts_count',
        'replies_count',
        'quotes_count',
        'raw_payload_json',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'raw_payload_json' => 'array',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function totalEngagement(): int
    {
        return $this->favourites_count + $this->boosts_count + $this->replies_count + $this->quotes_count;
    }
}
