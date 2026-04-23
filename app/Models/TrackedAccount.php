<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'acct_input',
        'username',
        'instance_domain',
        'acct_normalized',
        'remote_account_id',
        'account_url',
        'display_name',
        'avatar_url',
        'note_html',
        'followers_count_latest',
        'following_count_latest',
        'statuses_count_latest',
        'last_status_at_remote',
        'is_active',
        'last_resolved_at',
        'last_sync_started_at',
        'last_sync_finished_at',
        'last_sync_status',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_status_at_remote' => 'datetime',
            'last_resolved_at' => 'datetime',
            'last_sync_started_at' => 'datetime',
            'last_sync_finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(TrackedAccountSyncLog::class);
    }

    public function accountMetricSnapshots(): HasMany
    {
        return $this->hasMany(AccountMetricSnapshot::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
