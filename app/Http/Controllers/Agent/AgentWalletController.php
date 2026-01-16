<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentWalletController extends Controller
{
    /* =====================================================
     | HELPERS
     ===================================================== */

    /**
     * Resolve the agent ID from the authenticated user
     */
    protected function resolveAgentId(): int
    {
        $user = auth()->user();
        return (int) ($user->agent_id ?? $user->id);
    }

    /* =====================================================
     | GET /agent/wallets
     | List agent wallets (for dropdown / selection)
     ===================================================== */
    public function index(Request $request): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $wallets = Wallet::where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->orderBy('id')
            ->get([
                'id',
                'asset',
                'available_cents',
                'reserved_cents',
            ]);

        return response()->json([
            'data' => $wallets,
        ]);
    }

    /* =====================================================
     | GET /agent/wallets/{id}
     | Get a single wallet (ownership enforced)
     ===================================================== */
    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $wallet = Wallet::where('id', $id)
            ->where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->firstOrFail([
                'id',
                'asset',
                'available_cents',
                'reserved_cents',
            ]);

        return response()->json([
            'data' => $wallet,
        ]);
    }

    /* =====================================================
     | POST /agent/wallets/ensure-diamonds
     | Ensure agent has a DIAMONDS wallet
     | (idempotent)
     ===================================================== */
    public function ensureDiamondsWallet(Request $request): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $wallet = DB::transaction(function () use ($agentId) {
            $wallet = Wallet::where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->whereRaw('UPPER(asset) = ?', ['DIAMONDS'])
                ->lockForUpdate()
                ->first();

            if ($wallet) {
                return $wallet;
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
            'data' => [
                'id'              => $wallet->id,
                'asset'           => $wallet->asset,
                'available_cents' => $wallet->available_cents,
                'reserved_cents'  => $wallet->reserved_cents,
            ],
        ]);
    }
}
