<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Agent\OfflineWithdrawalController;

Route::prefix('agent')->group(function () {
    Route::get('/withdrawals/list', [OfflineWithdrawalController::class, 'list'])
        ->name('console.agent.withdrawals.list');

    Route::post('/withdrawals', [OfflineWithdrawalController::class, 'store'])
        ->name('console.agent.withdrawals.store');
});
