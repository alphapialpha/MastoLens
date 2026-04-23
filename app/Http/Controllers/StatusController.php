<?php

namespace App\Http\Controllers;

use App\Models\Status;

class StatusController extends Controller
{
    public function show(Status $status)
    {
        // Authorization: user can only view statuses from their own tracked accounts
        if ($status->trackedAccount->user_id !== auth()->id()) {
            abort(403);
        }

        $status->load(['trackedAccount', 'summary', 'metricSnapshots' => function ($q) {
            $q->orderBy('snapshot_target_age_minutes');
        }]);

        // Prepare chart data for the view
        // Prepend a synthetic 'Posted' baseline point (0 interactions at time of posting)
        $chartData = collect([[
            'label' => 'Posted',
            'favourites' => 0,
            'boosts' => 0,
            'replies' => 0,
            'total' => 0,
        ]])->concat($status->metricSnapshots->map(function ($snap) {
            $mins = $snap->snapshot_target_age_minutes;
            if ($mins === 0) {
                $label = 'Initial';
            } elseif ($mins >= 1440) {
                $label = round($mins / 1440, 1) . 'd';
            } elseif ($mins >= 60) {
                $label = round($mins / 60, 1) . 'h';
            } else {
                $label = $mins . 'm';
            }

            return [
                'label' => $label,
                'favourites' => $snap->favourites_count,
                'boosts' => $snap->boosts_count,
                'replies' => $snap->replies_count,
                'total' => $snap->totalEngagement(),
            ];
        })->values())->values();

        return view('statuses.show', compact('status', 'chartData'));
    }
}
