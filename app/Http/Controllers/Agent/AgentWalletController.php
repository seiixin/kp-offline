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
     * Resolve the logged-in agent ID
     * The logged-in user IS the agent
     */
    protected function resolveAgentId(): int
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        return (int) $user->id;
    }

    protected function cashWallet(int $agentId): Wallet
    {
        return Wallet::where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->whereRaw('UPPER(asset) = ?', ['PHP'])
            ->firstOrFail();
    }

    protected function diamondsWallet(int $agentId): Wallet
    {
        return Wallet::where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->whereRaw('UPPER(asset) = ?', ['DIAMONDS'])
            ->firstOrFail();
    }

    /* =====================================================
     | GET /agent/wallets
     | Legacy / dropdown support (ALL wallets)
     ===================================================== */
    public function index(): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        return response()->json([
            'data' => Wallet::where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->orderBy('id')
                ->get([
                    'id',
                    'asset',
                    'available_cents',
                    'reserved_cents',
                ]),
        ]);
    }

    /* =====================================================
     | GET /agent/wallet/overview
     | Used by UI cards (CASH + DIAMONDS)
     ===================================================== */
    public function overview(MongoEconomyService $mongo): JsonResponse
    {
        $agentId = $this->resolveAgentId();
        $user    = auth()->user();

        /* ===============================
        | 1. CASH (MYSQL)
        =============================== */
        $wallets = Wallet::where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->get()
            ->keyBy(fn ($w) => strtoupper($w->asset));

        $cash = $wallets->get('PHP');

        /* ===============================
        | 2. COINS + DIAMONDS (MONGODB)
        =============================== */
        $mongoWallet = null;

        if ($user && $user->mongo_user_id) {
            $mongoWallet = $mongo->getLoggedInAgentWallet($user->mongo_user_id);
        }

        return response()->json([
            'data' => [
                'cash' => $cash ? [
                    'asset'           => 'PHP',
                    'available_cents' => $cash->available_cents,
                    'reserved_cents'  => $cash->reserved_cents,
                ] : null,

                'diamonds' => [
                    'asset'   => 'DIAMONDS',
                    'balance' => $mongoWallet['wallet']['diamonds'] ?? 0,
                ],

                'coins' => [
                    'asset'   => 'COINS',
                    'balance' => $mongoWallet['wallet']['coins'] ?? 0,
                ],
            ],
        ]);
    }

    /* =====================================================
     | GET /agent/wallet/cash-summary
     | CASH wallet only (used by recharge / withdrawal modals)
     ===================================================== */
    public function cashSummary(): JsonResponse
    {
        $agentId = $this->resolveAgentId();
        $wallet  = $this->cashWallet($agentId);

        return response()->json([
            'data' => [
                'asset'           => 'PHP',
                'available_cents' => $wallet->available_cents,
                'reserved_cents'  => $wallet->reserved_cents,
            ],
        ]);
    }

    /* =====================================================
     | GET /agent/wallet/cash-ledger
     | CASH ledger ONLY (DIAMONDS NEVER USE LEDGER)
     ===================================================== */
    public function cashLedger(Request $request): JsonResponse
    {
        $agentId = $this->resolveAgentId();
        $wallet  = $this->cashWallet($agentId);

        $entries = LedgerEntry::where('wallet_id', $wallet->id)
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

    /* =====================================================
     | POST /agent/wallets/ensure-diamonds
     | Idempotent DIAMONDS wallet creation
     ===================================================== */
    public function ensureDiamondsWallet(): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $wallet = DB::transaction(function () use ($agentId) {
            $existing = Wallet::where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->whereRaw('UPPER(asset) = ?', ['DIAMONDS'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return Wallet::create([
                'user_id'         => $agentId,
                'owner_type'      => 'agent',
                'owner_id'        => $agentId,
                'asset'           => 'DIAMONDS',
                'available_cents' => 0,
                'reserved_cents'  => 0,
            ]);
        });

        return response()->json([
            'data' => $wallet->only([
                'id',
                'asset',
                'available_cents',
                'reserved_cents',
            ]),
        ]);
    }
}
