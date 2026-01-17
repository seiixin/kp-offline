<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    /* =====================================================
     | GET /admin/wallets
     | List ALL wallets (admin-wide)
     | Optional: ?agent_id=123
     ===================================================== */
    public function index(Request $request): JsonResponse
    {
        $query = Wallet::query()
            ->orderByDesc('id');

        if ($request->filled('agent_id')) {
            $query->where('owner_type', 'agent')
                  ->where('owner_id', (int) $request->agent_id);
        }

        $wallets = $query->get([
            'id',
            'owner_type',
            'owner_id',
            'asset',
            'available_cents',
            'reserved_cents',
            'created_at',
        ]);

        return response()->json([
            'data' => $wallets,
        ]);
    }

    /* =====================================================
     | GET /admin/wallets/{id}
     | Inspect a single wallet
     ===================================================== */
    public function show(int $id): JsonResponse
    {
        $wallet = Wallet::where('id', $id)->firstOrFail([
            'id',
            'owner_type',
            'owner_id',
            'asset',
            'available_cents',
            'reserved_cents',
            'created_at',
        ]);

        return response()->json([
            'data' => $wallet,
        ]);
    }

    /* =====================================================
     | GET /admin/wallets/{id}/ledger
     | Inspect ledger entries of any wallet
     ===================================================== */
    public function ledger(int $id): JsonResponse
    {
        $wallet = Wallet::where('id', $id)->firstOrFail();

        $entries = LedgerEntry::where('wallet_id', $wallet->id)
            ->latest()
            ->limit(100)
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
