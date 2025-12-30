<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagesController;

Route::get('/', function () {
    return redirect()->route('console.dashboard');
});

// Breeze provides auth routes via routes/auth.php
require __DIR__.'/auth.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // MAIN
    Route::get('/dashboard', [PagesController::class, 'dashboard'])->name('console.dashboard');

    // AGENT
    Route::get('/agent', [PagesController::class, 'agentDashboard'])->name('console.agent.dashboard');
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
});
