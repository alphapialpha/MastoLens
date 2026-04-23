<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Status extends Model
{
    use HasFactory;
    protected $fillable = [
        'tracked_account_id',
        'instance_domain',
        'remote_status_id',
        'status_url',
        'uri',
        'created_at_remote',
        'edited_at_remote',
        'fetched_first_at',
        'fetched_last_at',
        'content_html',
        'content_text',
        'spoiler_text',
        'language',
        'visibility',
        'is_sensitive',
        'is_reply',
        'is_boost',
        'has_media',
        'has_poll',
        'has_card',
        'in_reply_to_remote_status_id',
        'in_reply_to_remote_account_id',
        'boost_of_remote_status_id',
        'boost_data_json',
        'media_count',
        'media_attachments_json',
        'mentions_json',
        'tags_json',
        'emojis_json',
        'poll_json',
        'card_json',
        'raw_payload_json',
        'tracking_state',
        'next_snapshot_due_at',
        'archived_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at_remote' => 'datetime',
            'edited_at_remote' => 'datetime',
            'fetched_first_at' => 'datetime',
            'fetched_last_at' => 'datetime',
            'next_snapshot_due_at' => 'datetime',
            'archived_at' => 'datetime',
            'failed_at' => 'datetime',
            'is_sensitive' => 'boolean',
            'is_reply' => 'boolean',
            'is_boost' => 'boolean',
            'has_media' => 'boolean',
            'has_poll' => 'boolean',
            'has_card' => 'boolean',
            'boost_data_json' => 'array',
            'media_attachments_json' => 'array',
            'mentions_json' => 'array',
            'tags_json' => 'array',
            'emojis_json' => 'array',
            'poll_json' => 'array',
            'card_json' => 'array',
            'raw_payload_json' => 'array',
        ];
    }

    public function trackedAccount(): BelongsTo
    {
        return $this->belongsTo(TrackedAccount::class);
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(StatusMetricSnapshot::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(StatusSummary::class);
    }

    public function scopeActive($query)
    {
        return $query->where('tracking_state', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('tracking_state', 'archived');
    }

    public function scopeFailed($query)
    {
        return $query->where('tracking_state', 'failed');
    }

    public function scopeDueForSnapshot($query)
    {
        return $query->whereNotNull('next_snapshot_due_at')
            ->where('next_snapshot_due_at', '<=', now())
            ->where('tracking_state', 'active');
    }
}
