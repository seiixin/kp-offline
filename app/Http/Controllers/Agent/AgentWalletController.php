<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentWalletController extends Controller
{
    /* =====================================================
     | HELPERS
     ===================================================== */

    protected function resolveAgentId(): int
    {
        $user = auth()->user();
        return (int) ($user->agent_id ?? $user->id);
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
     | Dropdown / legacy support
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
     | GET /agent/wallets/{id}
     | Legacy single wallet fetch
     ===================================================== */
    public function show(int $id): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        return response()->json([
            'data' => Wallet::where('id', $id)
                ->where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->firstOrFail([
                    'id',
                    'asset',
                    'available_cents',
                    'reserved_cents',
                ]),
        ]);
    }

    /* =====================================================
     | POST /agent/wallets/ensure-diamonds
     | Idempotent wallet creation
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

    /* =====================================================
     | GET /agent/wallet/summary
     | Used by wallet cards (AVAILABLE / RESERVED)
     ===================================================== */
    public function summary(): JsonResponse
    {
        $agentId = $this->resolveAgentId();
        $wallet  = $this->diamondsWallet($agentId);

        return response()->json([
            'data' => [
                'asset'           => $wallet->asset,
                'available_cents' => $wallet->available_cents,
                'reserved_cents'  => $wallet->reserved_cents,
            ],
        ]);
    }

    /* =====================================================
     | GET /agent/wallet/ledger
     | Used by wallet table
     ===================================================== */
    public function ledger(Request $request): JsonResponse
    {
        $agentId = $this->resolveAgentId();
        $wallet  = $this->diamondsWallet($agentId);

        $entries = LedgerEntry::where('wallet_id', $wallet->id)
            ->latest()
            ->limit(50)
            ->get([
                'id',
                'event_type',
                'direction',
                'amount_cents',
                'meta',
                'created_at',
            ]);

        return response()->json([
            'data' => $entries,
        ]);
    }
}
