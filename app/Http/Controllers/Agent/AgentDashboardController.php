<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AgentDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();

        $wallet = $this->findWalletForAgent($userId);

        [$available, $reserved, $currency] = $this->extractWalletAmounts($wallet);

        $rechargesToday = $this->countTodayForAgentTable('offline_recharges', $userId);
        $withdrawalsToday = $this->countTodayForAgentTable('offline_withdrawals', $userId);

        return Inertia::render('Console/Agent/Dashboard', [
            'active' => 'agent.dashboard',
            'wallet' => [
                'available' => $available,
                'reserved'  => $reserved,
                'currency'  => $currency, // e.g. PHP / USD
            ],
            'today' => [
                'recharges'   => $rechargesToday,
                'withdrawals' => $withdrawalsToday,
            ],
        ]);
    }

    private function findWalletForAgent(int $userId)
    {
        $walletModel = 'App\\Models\\Wallet';
        if (!class_exists($walletModel)) return null;

        $walletInstance = new $walletModel();
        $table = method_exists($walletInstance, 'getTable') ? $walletInstance->getTable() : 'wallets';

        if (!Schema::hasTable($table)) return null;

        // ✅ Your schema: owner_type + owner_id (polymorphic-style)
        if (Schema::hasColumn($table, 'owner_type') && Schema::hasColumn($table, 'owner_id')) {
            // Agent dashboard: prefer owner_type=agent
            $w = $walletModel::query()
                ->where('owner_type', 'agent')
                ->where('owner_id', $userId)
                ->first();

            // fallback if some env uses owner_type=user
            if (!$w) {
                $w = $walletModel::query()
                    ->whereIn('owner_type', ['user', 'player'])
                    ->where('owner_id', $userId)
                    ->first();
            }

            return $w;
        }

        // Fallback (older schema)
        foreach (['user_id', 'agent_user_id', 'agent_id', 'created_by_user_id'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $walletModel::query()->where($col, $userId)->first();
            }
        }

        return null;
    }

    /**
     * Returns [available, reserved, currency]
     */
    private function extractWalletAmounts($wallet): array
    {
        if (!$wallet) {
            return [0.0, 0.0, 'PHP'];
        }

        $arr = method_exists($wallet, 'toArray') ? $wallet->toArray() : (array) $wallet;

        // Detect currency from asset if present
        $asset = (string)($arr['asset'] ?? '');
        $currency = $this->guessCurrencyFromAsset($asset);

        // ✅ Your columns are *_cents
        if (array_key_exists('available_cents', $arr) || array_key_exists('reserved_cents', $arr)) {
            $availableCents = (float)($arr['available_cents'] ?? 0);
            $reservedCents  = (float)($arr['reserved_cents'] ?? 0);

            return [
                $availableCents / 100.0,
                $reservedCents / 100.0,
                $currency,
            ];
        }

        // Fallback (non-cents)
        $available = $this->readAmountFromKeys($arr, [
            'available_balance', 'available', 'balance', 'amount_available',
        ]);

        $reserved = $this->readAmountFromKeys($arr, [
            'reserved_balance', 'reserved', 'amount_reserved',
        ]);

        return [$available, $reserved, $currency];
    }

    private function guessCurrencyFromAsset(string $asset): string
    {
        $a = strtoupper($asset);
        if (str_contains($a, 'USD')) return 'USD';
        if (str_contains($a, 'PHP') || str_contains($a, 'PHP_CENTS')) return 'PHP';
        // default display currency
        return 'PHP';
    }

    private function readAmountFromKeys(array $arr, array $keys): float
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null) {
                return (float) $arr[$k];
            }
        }
        return 0.0;
    }

    private function countTodayForAgentTable(string $table, int $agentUserId): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $q = DB::table($table)->whereDate('created_at', now()->toDateString());

        foreach (['agent_id', 'agent_user_id', 'created_by', 'created_by_user_id', 'user_id', 'owner_id'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                $q->where($col, $agentUserId);
                break;
            }
        }

        return (int) $q->count();
    }
}
