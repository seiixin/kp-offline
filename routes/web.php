<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\Agent\AgentDashboardController;
use App\Http\Controllers\Agent\OfflineRechargeController;
use App\Http\Controllers\Agent\UserDropdownController;


Route::get('/', function () {
    return redirect()->route('console.dashboard');
});

// Breeze provides auth routes via routes/auth.php
require __DIR__ . '/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // MAIN
    Route::get('/dashboard', [PagesController::class, 'dashboard'])->name('console.dashboard');

    // AGENT
    Route::get('/agent', [AgentDashboardController::class, 'index'])->name('console.agent.dashboard');
    Route::get('/recharges', [PagesController::class, 'recharges'])->name('console.agent.recharges');
    Route::get('/withdrawals', [PagesController::class, 'withdrawals'])->name('console.agent.withdrawals');
    Route::get('/wallet', [PagesController::class, 'wallet'])->name('console.agent.wallet');
    Route::get('/commissions', [PagesController::class, 'commissions'])->name('console.agent.commissions');

    // ADMIN
    Route::prefix('admin')->group(function () {
        Route::get('/overview', [PagesController::class, 'adminOverview'])->name('console.admin.overview');
        Route::get('/agents', [PagesController::class, 'agents'])->name('console.admin.agents');
        Route::get('/top-ups', [PagesController::class, 'topUps'])->name('console.admin.topups');
        Route::get('/audit-log', [PagesController::class, 'auditLog'])->name('console.admin.auditlog');
    });

    Route::prefix('agent')->group(function () {
        Route::get('/recharges/list', [OfflineRechargeController::class, 'list'])->name('console.agent.recharges.list');
        Route::post('/recharges', [OfflineRechargeController::class, 'store'])->name('console.agent.recharges.store');
        Route::get('/users/dropdown', [UserDropdownController::class,'index']);

    });

});
// Module 5 - Agent Withdrawals routes
require __DIR__ . '/module5_agent_withdrawals.php';
