<?php

use App\Http\Controllers\FailedStatusController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TrackedAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        $accounts = $user->trackedAccounts()->orderByDesc('created_at')->get();
        $accountCount = $accounts->count();
        $activeCount = $accounts->where('is_active', true)->count();
        $statusCount = \App\Models\Status::whereIn(
            'tracked_account_id',
            $user->trackedAccounts()->select('id')
        )->count();

        // Failed count per account (for badge in table)
        $failedCounts = \App\Models\Status::whereIn(
                'tracked_account_id',
                $user->trackedAccounts()->select('id')
            )
            ->where('tracking_state', 'failed')
            ->selectRaw('tracked_account_id, count(*) as failed_count')
            ->groupBy('tracked_account_id')
            ->pluck('failed_count', 'tracked_account_id');

        $failedCount = $failedCounts->sum();

        return view('dashboard', compact(
            'accounts', 'accountCount', 'activeCount', 'statusCount',
            'failedCounts', 'failedCount'
        ));
    })->name('dashboard');

    Route::get('/tracked-accounts', [TrackedAccountController::class, 'index'])->name('tracked-accounts.index');
    Route::get('/tracked-accounts/create', [TrackedAccountController::class, 'create'])->name('tracked-accounts.create');
    Route::post('/tracked-accounts', [TrackedAccountController::class, 'store'])->name('tracked-accounts.store');
    Route::get('/tracked-accounts/{trackedAccount}', [TrackedAccountController::class, 'show'])->name('tracked-accounts.show');
    Route::get('/tracked-accounts/{trackedAccount}/archive', [TrackedAccountController::class, 'archive'])->name('tracked-accounts.archive');
    Route::patch('/tracked-accounts/{trackedAccount}/toggle', [TrackedAccountController::class, 'toggleActive'])->name('tracked-accounts.toggle');
    Route::delete('/tracked-accounts/{trackedAccount}', [TrackedAccountController::class, 'destroy'])->name('tracked-accounts.destroy');

    Route::get('/statuses/{status}', [StatusController::class, 'show'])->name('statuses.show');

    Route::post('/statuses/{status}/retry', [FailedStatusController::class, 'retry'])->name('statuses.retry');
    Route::post('/statuses/{status}/archive', [FailedStatusController::class, 'archive'])->name('statuses.archive');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/profile/two-factor/disable', [ProfileController::class, 'disableTwoFactor'])->name('profile.two-factor.disable');
});
