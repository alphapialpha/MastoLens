<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackedAccountSyncLog extends Model
{
    protected $fillable = [
        'tracked_account_id',
        'job_type',
        'started_at',
        'finished_at',
        'status',
        'posts_examined',
        'new_posts_count',
        'updated_posts_count',
        'snapshots_created_count',
        'error_message',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta_json' => 'array',
        ];
    }

    public function trackedAccount(): BelongsTo
    {
        return $this->belongsTo(TrackedAccount::class);
    }
}
