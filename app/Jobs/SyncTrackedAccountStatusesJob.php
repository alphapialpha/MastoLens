<?php

namespace App\Jobs;

use App\Models\AccountMetricSnapshot;
use App\Models\TrackedAccount;
use App\Models\TrackedAccountSyncLog;
use App\Services\MastodonApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncTrackedAccountStatusesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(
        public TrackedAccount $trackedAccount,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-account-' . $this->trackedAccount->id;
    }

    public function handle(MastodonApiService $mastodon): void
    {
        $account = $this->trackedAccount;

        if (!$account->is_active) {
            return;
        }

        $syncLog = TrackedAccountSyncLog::create([
            'tracked_account_id' => $account->id,
            'job_type' => 'sync_statuses',
            'started_at' => now(),
            'status' => 'running',
        ]);

        $account->update(['last_sync_started_at' => now()]);

        try {
            $statuses = $mastodon->getAccountStatuses(
                $account->instance_domain,
                $account->remote_account_id,
                20
            );

            if ($statuses === null) {
                throw new \RuntimeException('Failed to fetch statuses from instance');
            }

            $newCount = 0;
            $updatedCount = 0;

            foreach ($statuses as $statusData) {
                $result = $this->processStatus($account, $statusData);
                if ($result === 'new') {
                    $newCount++;
                } elseif ($result === 'updated') {
                    $updatedCount++;
                }
            }

            // Update account metadata from API
            $accountData = $mastodon->lookupAccount($account->username, $account->instance_domain);
            if ($accountData) {
                $account->update([
                    'display_name' => $accountData['display_name'] ?? $account->display_name,
                    'avatar_url' => $accountData['avatar_static'] ?? $accountData['avatar'] ?? $account->avatar_url,
                    'followers_count_latest' => $accountData['followers_count'] ?? $account->followers_count_latest,
                    'following_count_latest' => $accountData['following_count'] ?? $account->following_count_latest,
                    'statuses_count_latest' => $accountData['statuses_count'] ?? $account->statuses_count_latest,
                    'last_status_at_remote' => $accountData['last_status_at'] ?? $account->last_status_at_remote,
                ]);
            }

            // Capture daily account metric snapshot (one per day, idempotent)
            $today = now()->toDateString();
            if ($account->followers_count_latest !== null) {
                try {
                    AccountMetricSnapshot::firstOrCreate(
                        [
                            'tracked_account_id' => $account->id,
                            'snapshot_date' => $today,
                        ],
                        [
                            'followers_count' => $account->followers_count_latest,
                            'following_count' => $account->following_count_latest ?? 0,
                            'statuses_count' => $account->statuses_count_latest ?? 0,
                            'captured_at' => now(),
                        ]
                    );
                } catch (UniqueConstraintViolationException) {
                    // Already exists for today, skip
                }
            }

            $account->update([
                'last_sync_finished_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_error' => null,
            ]);

            $syncLog->update([
                'finished_at' => now(),
                'status' => 'success',
                'posts_examined' => count($statuses),
                'new_posts_count' => $newCount,
                'updated_posts_count' => $updatedCount,
            ]);

        } catch (\Throwable $e) {
            $account->update([
                'last_sync_finished_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_error' => mb_substr($e->getMessage(), 0, 255),
            ]);

            $syncLog->update([
                'finished_at' => now(),
                'status' => 'error',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }

    private function processStatus(TrackedAccount $account, array $data): string
    {
        $remoteId = (string) $data['id'];
        $existing = $account->statuses()
            ->where('remote_status_id', $remoteId)
            ->first();

        if ($existing) {
            $updateData = ['fetched_last_at' => now()];

            // Backfill media attachments if not yet stored
            if ($existing->media_attachments_json === null && !empty($data['media_attachments'])) {
                $updateData['media_attachments_json'] = $data['media_attachments'];
            }

            // Backfill boost data if not yet stored
            if ($existing->is_boost && $existing->boost_data_json === null && !empty($data['reblog'])) {
                $reblog = $data['reblog'];
                $updateData['boost_data_json'] = $this->extractBoostData($reblog);

                // Also backfill content/media from the original post if the boost was stored empty
                if (empty($existing->content_html) && !empty($reblog['content'])) {
                    $updateData['content_html'] = $reblog['content'];
                    $updateData['content_text'] = strip_tags($reblog['content']);
                }
                if (empty($existing->status_url) && !empty($reblog['url'])) {
                    $updateData['status_url'] = $reblog['url'];
                }
                if ($existing->media_attachments_json === null && !empty($reblog['media_attachments'])) {
                    $updateData['media_attachments_json'] = $reblog['media_attachments'];
                    $updateData['has_media'] = true;
                    $updateData['media_count'] = count($reblog['media_attachments']);
                }
            }

            $existing->update($updateData);
            return 'updated';
        }

        $createdAt = isset($data['created_at'])
            ? Carbon::parse($data['created_at'])
            : null;

        $reblog = $data['reblog'] ?? null;
        $isBoost = $reblog !== null;

        // For boosts: use the original post's content/media since the outer object is empty
        $contentSource = $isBoost ? $reblog : $data;

        $status = $account->statuses()->create([
            'instance_domain' => $account->instance_domain,
            'remote_status_id' => $remoteId,
            'status_url' => $data['url'] ?? ($isBoost ? ($reblog['url'] ?? null) : null),
            'uri' => $data['uri'] ?? null,
            'created_at_remote' => $createdAt,
            'fetched_first_at' => now(),
            'fetched_last_at' => now(),
            'content_html' => $contentSource['content'] ?? null,
            'content_text' => strip_tags($contentSource['content'] ?? ''),
            'spoiler_text' => $contentSource['spoiler_text'] ?? null,
            'language' => $contentSource['language'] ?? $data['language'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
            'is_sensitive' => $contentSource['sensitive'] ?? false,
            'is_reply' => !empty($data['in_reply_to_id']),
            'is_boost' => $isBoost,
            'has_media' => !empty($contentSource['media_attachments']),
            'has_poll' => !empty($contentSource['poll']),
            'has_card' => !empty($contentSource['card']),
            'in_reply_to_remote_status_id' => $data['in_reply_to_id'] ?? null,
            'in_reply_to_remote_account_id' => $data['in_reply_to_account_id'] ?? null,
            'boost_of_remote_status_id' => $isBoost ? (string) ($reblog['id'] ?? null) : null,
            'boost_data_json' => $isBoost && $reblog ? $this->extractBoostData($reblog) : null,
            'media_count' => count($contentSource['media_attachments'] ?? []),
            'media_attachments_json' => !empty($contentSource['media_attachments']) ? $contentSource['media_attachments'] : null,
            'mentions_json' => $data['mentions'] ?? null,
            'tags_json' => $data['tags'] ?? null,
            'emojis_json' => $data['emojis'] ?? null,
            'poll_json' => $contentSource['poll'] ?? null,
            'card_json' => $contentSource['card'] ?? null,
            'tracking_state' => 'active',
            'next_snapshot_due_at' => now(),
        ]);

        // If post is older than 30 days, archive immediately
        if ($createdAt && $createdAt->lt(now()->subDays(30))) {
            $this->captureInitialSnapshot($status, $data);
            $status->update([
                'tracking_state' => 'archived',
                'archived_at' => now(),
                'next_snapshot_due_at' => null,
            ]);
        } else {
            // Capture the 0-minute snapshot inline
            $this->captureInitialSnapshot($status, $data);
            $nextDue = $this->calculateNextSnapshotDue($createdAt);

            if ($nextDue) {
                $status->update(['next_snapshot_due_at' => $nextDue]);
            } else {
                // All snapshot targets already passed — archive immediately
                $status->update([
                    'tracking_state' => 'archived',
                    'archived_at' => now(),
                    'next_snapshot_due_at' => null,
                ]);
            }
        }

        return 'new';
    }

    private function captureInitialSnapshot($status, array $data): void
    {
        $ageMinutes = $status->created_at_remote
            ? max(0, (int) $status->created_at_remote->diffInMinutes(now()))
            : 0;

        $favourites = $data['favourites_count'] ?? 0;
        $boosts = $data['reblogs_count'] ?? 0;
        $replies = $data['replies_count'] ?? 0;

        $status->metricSnapshots()->create([
            'captured_at' => now(),
            'snapshot_target_age_minutes' => 0,
            'actual_age_minutes' => $ageMinutes,
            'favourites_count' => $favourites,
            'boosts_count' => $boosts,
            'replies_count' => $replies,
            'quotes_count' => 0,
        ]);

        $totalEngagement = $favourites + $boosts + $replies;

        $status->summary()->updateOrCreate(
            ['status_id' => $status->id],
            [
                'latest_snapshot_at' => now(),
                'latest_favourites_count' => $favourites,
                'latest_boosts_count' => $boosts,
                'latest_replies_count' => $replies,
                'latest_quotes_count' => 0,
                'snapshot_count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'peak_total_engagement' => $totalEngagement,
            ]
        );
    }

    private function calculateNextSnapshotDue(?Carbon $createdAt): ?Carbon
    {
        if (!$createdAt) {
            return now()->addMinutes(15);
        }

        $ageMinutes = max(0, (int) $createdAt->diffInMinutes(now()));
        $targets = CaptureStatusSnapshotJob::SNAPSHOT_TARGETS;

        foreach ($targets as $target) {
            if ($target === 0) {
                continue; // 0m captured inline during discovery
            }
            if ($target > $ageMinutes || $ageMinutes <= $target * 1.5) {
                $dueAt = $createdAt->copy()->addMinutes($target);
                return $dueAt->lt(now()) ? now() : $dueAt;
            }
        }

        return null; // All targets passed — will be archived
    }

    private function extractBoostData(array $reblog): array
    {
        $account = $reblog['account'] ?? [];

        return [
            'author_acct' => $account['acct'] ?? null,
            'author_display_name' => $account['display_name'] ?? $account['username'] ?? null,
            'author_avatar' => $account['avatar_static'] ?? $account['avatar'] ?? null,
            'author_url' => $account['url'] ?? null,
            'original_url' => $reblog['url'] ?? null,
            'content_html' => $reblog['content'] ?? null,
            'spoiler_text' => $reblog['spoiler_text'] ?? null,
            'language' => $reblog['language'] ?? null,
            'media_attachments' => !empty($reblog['media_attachments']) ? $reblog['media_attachments'] : null,
            'created_at' => $reblog['created_at'] ?? null,
        ];
    }
}
