<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PagesController extends Controller
{
    // MAIN
    public function dashboard(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'active' => 'dashboard',
        ]);
    }

    // AGENT
    public function agentDashboard(): Response
    {
        return Inertia::render('Admin/AgentDashboard', [
            'active' => 'agent_dashboard',
        ]);
    }

    public function recharges(): Response
    {
        return Inertia::render('Admin/Recharges', [
            'active' => 'recharges',
        ]);
    }

    public function withdrawals(): Response
    {
        return Inertia::render('Admin/Withdrawals', [
            'active' => 'withdrawals',
        ]);
    }

    public function wallet(): Response
    {
        return Inertia::render('Admin/Wallet', [
            'active' => 'wallet',
        ]);
    }

    public function commissions(): Response
    {
        return Inertia::render('Admin/Commissions', [
            'active' => 'commissions',
        ]);
    }

    // ADMIN
    public function adminOverview(): Response
    {
        return Inertia::render('Admin/AdminOverview', [
            'active' => 'admin_overview',
        ]);
    }

    public function agents(): Response
    {
        return Inertia::render('Admin/Agents', [
            'active' => 'admin_agents',
        ]);
    }

    public function topUps(): Response
    {
        return Inertia::render('Admin/TopUps', [
            'active' => 'admin_topups',
        ]);
    }

    public function auditLog(): Response
    {
        return Inertia::render('Admin/AuditLog', [
            'active' => 'admin_audit_log',
        ]);
    }
}
