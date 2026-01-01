<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Agent\OfflineRechargeController;

/*
| Module 4 — Agent Offline Recharges
| Paste these routes inside your auth+verified group.
*/

Route::prefix('agent')->group(function () {
    Route::get('/recharges/list', [OfflineRechargeController::class, 'list'])->name('console.agent.recharges.list');
    Route::post('/recharges', [OfflineRechargeController::class, 'store'])->name('console.agent.recharges.store');
});
