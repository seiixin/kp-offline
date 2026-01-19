<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\Agent\AgentDashboardController;
use App\Http\Controllers\Agent\OfflineRechargeController;
use App\Http\Controllers\Agent\UserDropdownController;
use App\Http\Controllers\Agent\AgentWalletController;
use App\Http\Controllers\Admin\OfflineWithdrawalController;
use App\Http\Controllers\Admin\AgentsController;
use App\Http\Controllers\Admin\AuditLogsController;
use App\Http\Controllers\Admin\TopupController;

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
        Route::get('/agents/index', [PagesController::class, 'agents'])->name('console.admin.agents');
        Route::get('/top-ups', [PagesController::class, 'topUps'])->name('console.admin.topups');
        Route::get('/audit-log', [PagesController::class, 'auditLog'])->name('console.admin.auditlog');
        Route::get('/withdrawals', [OfflineWithdrawalController::class, 'index']);
        Route::put('/withdrawals/{id}', [OfflineWithdrawalController::class, 'update']); 
        Route::get('/wallets', [AdminWalletController::class, 'index']);
        Route::get('/wallets/{id}', [AdminWalletController::class, 'show']);
        Route::get('/wallets/{id}/ledger', [AdminWalletController::class, 'ledger']);
        
        // AGENTS CONTROLLER
        Route::get('/agents', [AgentsController::class, 'index']);
        Route::post('/agents', [AgentsController::class, 'store']);
        Route::put('/agents/{user}', [AgentsController::class, 'update']);
        Route::delete('/agents/{user}', [AgentsController::class, 'destroy']);

        Route::get('/agency-members-dropdown', [AgentsController::class, 'agencyMembersDropdown']);

        // AUDIT LOGS CONTROLLER
        Route::get('/audit-logs', [AuditLogsController::class, 'index']);
        Route::get('/audit-logs/export/excel', [AuditLogsController::class, 'exportExcel']);
        Route::get('/audit-logs/export/pdf', [AuditLogsController::class, 'exportPdf']);

        // TOP UP CONTROLLER
        // Route to retrieve Stripe public and secret keys (GET)
        Route::get('/stripe-keys', [TopupController::class, 'getStripeKeys'])
            ->name('admin.stripe.keys'); // Admin retrieves the Stripe keys

        // Route to initiate Stripe top-up for an agent (POST)
        Route::post('/topups/{agentId}/stripe', [TopupController::class, 'adminTopUp'])
            ->name('admin.topups.stripe'); // Admin initiates top-up via Stripe

        // Route to confirm Stripe top-up after payment is completed (POST)
        Route::post('/topups/{agentId}/stripe-complete', [TopupController::class, 'completeTopUp'])
            ->name('admin.topups.stripe.complete'); // Admin confirms top-up after payment
        });

    Route::prefix('agent')->group(function () {
        Route::get('/recharges/list', [OfflineRechargeController::class, 'list'])->name('console.agent.recharges.list');
        Route::post('/recharges', [OfflineRechargeController::class, 'store'])->name('console.agent.recharges.store');
        Route::get('/users/dropdown', [UserDropdownController::class,'index']);

    });

    Route::prefix('agent')->group(function () {
        Route::get('/wallets', [AgentWalletController::class, 'index']);

        Route::get('/wallet/overview', [AgentWalletController::class, 'overview']);

        Route::get('/wallet/cash-summary', [AgentWalletController::class, 'cashSummary']);
        Route::get('/wallet/cash-ledger',  [AgentWalletController::class, 'cashLedger']);

        Route::post('/wallets/ensure-diamonds', [
            AgentWalletController::class,
            'ensureDiamondsWallet'
        ]);
    });


});
// Module 5 - Agent Withdrawals routes
require __DIR__ . '/module5_agent_withdrawals.php';
