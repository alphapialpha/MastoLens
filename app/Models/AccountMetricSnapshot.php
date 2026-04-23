<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMetricSnapshot extends Model
{
    protected $fillable = [
        'tracked_account_id',
        'followers_count',
        'following_count',
        'statuses_count',
        'captured_at',
        'snapshot_date',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'snapshot_date' => 'date',
        ];
    }

    public function trackedAccount(): BelongsTo
    {
        return $this->belongsTo(TrackedAccount::class);
    }
}
