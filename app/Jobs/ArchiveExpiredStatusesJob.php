<?php

namespace App\Jobs;

use App\Models\Status;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ArchiveExpiredStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
        // Archive statuses older than 30 days that are still active
        $expired = Status::where('tracking_state', 'active')
            ->whereNotNull('created_at_remote')
            ->where('created_at_remote', '<', now()->subDays(30))
            ->limit(500)
            ->get();

        foreach ($expired as $status) {
            $status->update([
                'tracking_state' => 'archived',
                'archived_at' => now(),
                'next_snapshot_due_at' => null,
            ]);

            $status->summary?->update(['archived_at' => now()]);
        }
    }
}
