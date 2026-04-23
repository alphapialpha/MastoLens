<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Services\MastodonApiService;

class FailedStatusController extends Controller
{
    public function retry(Status $status, MastodonApiService $mastodon)
    {
        if ($status->trackedAccount->user_id !== auth()->id()) {
            abort(403);
        }

        if ($status->tracking_state !== 'failed') {
            return back()->with('error', 'Status is not in failed state.');
        }

        $result = $mastodon->getStatus(
            $status->instance_domain,
            $status->remote_status_id
        );

        if ($result['status'] === 'ok') {
            $status->update([
                'tracking_state' => 'active',
                'failed_at' => null,
                'next_snapshot_due_at' => now(),
            ]);

            return back()->with('success', 'Post is back online — tracking resumed.');
        }

        return back()->with('error', 'Post is still unavailable (HTTP ' . ($result['http_code'] ?? 'timeout') . ').');
    }

    public function archive(Status $status)
    {
        if ($status->trackedAccount->user_id !== auth()->id()) {
            abort(403);
        }

        if ($status->tracking_state !== 'failed') {
            return back()->with('error', 'Status is not in failed state.');
        }

        $status->update([
            'tracking_state' => 'archived',
            'archived_at' => now(),
            'next_snapshot_due_at' => null,
        ]);

        $status->summary?->update(['archived_at' => now()]);

        return back()->with('success', 'Post archived. Historical data preserved.');
    }
}
