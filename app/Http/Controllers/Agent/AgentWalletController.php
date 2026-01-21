<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\MongoEconomyService;

class AgentWalletController extends Controller
{
    /* =====================================================
     | HELPERS
     ===================================================== */

    /**
     * Logged-in user IS the agent
     */
    protected function resolveAgentId(): int
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        return (int) $user->id;
    }

    protected function cashWallet(int $agentId): ?Wallet
    {
        return Wallet::where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->whereRaw('UPPER(asset) = ?', ['PHP'])
            ->first();
    }

    /* =====================================================
     | GET /agent/wallet/overview
     | FRONTEND SOURCE OF TRUTH
     ===================================================== */
    public function overview(MongoEconomyService $mongo): JsonResponse
    {
        $agentId = $this->resolveAgentId();
        $user    = auth()->user();

        /* ===============================
        | 1) CASH (MYSQL)
        =============================== */
        $cashWallet = $this->cashWallet($agentId);

        /* ===============================
        | 2) COINS + DIAMONDS (MONGODB)
        =============================== */
        $mongoWallet = null;

        if ($user && $user->mongo_user_id) {
            $mongoWallet = $mongo->getLoggedInAgentWallet($user->mongo_user_id);
        }

        return response()->json([
            'data' => [
                'cash' => $cashWallet ? [
                    'asset'           => 'PHP',
                    'available_cents' => (int) $cashWallet->available_cents,
                    'reserved_cents'  => (int) $cashWallet->reserved_cents,
                ] : [
                    'asset'           => 'PHP',
                    'available_cents' => 0,
                    'reserved_cents'  => 0,
                ],

                'coins' => [
                    'asset'   => 'COINS',
                    'balance' => (int) ($mongoWallet['wallet']['coins'] ?? 0),
                ],

                'diamonds' => [
                    'asset'   => 'DIAMONDS',
                    'balance' => (int) ($mongoWallet['wallet']['diamonds'] ?? 0),
                ],
            ],
        ]);
    }

    /* =====================================================
     | GET /agent/wallet/cash-summary
     | Used by recharge / withdrawal modals
     ===================================================== */
    public function cashSummary(): JsonResponse
    {
        $agentId = $this->resolveAgentId();
        $wallet  = $this->cashWallet($agentId);

        if (!$wallet) {
            return response()->json([
                'data' => [
                    'asset'           => 'PHP',
                    'available_cents' => 0,
                    'reserved_cents'  => 0,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'asset'           => 'PHP',
                'available_cents' => (int) $wallet->available_cents,
                'reserved_cents'  => (int) $wallet->reserved_cents,
            ],
        ]);
    }

public function coinsLedger(): JsonResponse
{
    $user = auth()->user();

    if (!$user || !$user->mongo_user_id) {
        abort(401, 'Unauthenticated.');
    }

    $entries = LedgerEntry::query()
        ->where('currency', 'Coins')
        ->where('meta->mongo_user_id', $user->mongo_user_id)
        ->latest()
        ->limit(50)
        ->get([
            'id',
            'event_type',
            'direction',
            'amount_cents',
            'currency',
            'meta',
            'created_at',
        ]);

    return response()->json([
        'data' => $entries,
    ]);
}

}
