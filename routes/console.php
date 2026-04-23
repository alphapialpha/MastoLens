<?php

use App\Jobs\ArchiveExpiredStatusesJob;
use App\Jobs\CaptureStatusSnapshotJob;
use App\Jobs\SyncTrackedAccountStatusesJob;
use App\Models\Status;
use App\Models\TrackedAccount;
use Illuminate\Support\Facades\Schedule;

// Discovery: sync each active tracked account every 5 minutes
Schedule::call(function () {
    TrackedAccount::where('is_active', true)
        ->each(function (TrackedAccount $account) {
            SyncTrackedAccountStatusesJob::dispatch($account);
        });
})->everyFiveMinutes()->name('dispatch-account-syncs')->withoutOverlapping();

// Snapshot collection: dispatch jobs for statuses due for a snapshot
Schedule::call(function () {
    Status::where('tracking_state', 'active')
        ->whereNotNull('next_snapshot_due_at')
        ->where('next_snapshot_due_at', '<=', now())
        ->limit(200)
        ->each(function (Status $status) {
            CaptureStatusSnapshotJob::dispatch($status);
        });
})->everyMinute()->name('dispatch-snapshot-captures')->withoutOverlapping();

// Daily archive sweep
Schedule::job(new ArchiveExpiredStatusesJob)->daily()->name('archive-expired-statuses');
