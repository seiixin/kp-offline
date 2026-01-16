<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfflineWithdrawal;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfflineWithdrawalController extends Controller
{
    /* =====================================================
     | GET /admin/withdrawals
     | View all withdrawals (admin)
     ===================================================== */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:processing,successful,failed,cancelled'],
            'q'      => ['nullable', 'string', 'max:120'],
            'per'    => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = OfflineWithdrawal::query()->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['q'])) {
            $q = trim($validated['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('mongo_user_id', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        $page = $query->paginate($validated['per'] ?? 20);

        return response()->json([
            'data' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'data'         => $page->items(),
            ],
        ]);
    }

    /* =====================================================
     | PUT /admin/withdrawals/{id}
     | Admin finalizes withdrawal
     ===================================================== */
public function update(Request $request, int $id): JsonResponse
{
    $data = $request->validate([
        'status'    => ['required', 'in:successful,failed,cancelled'],
        'reference' => ['nullable', 'string', 'max:120'],
        'notes'     => ['nullable', 'string', 'max:500'],
    ]);

    return DB::transaction(function () use ($id, $data) {

        $withdrawal = OfflineWithdrawal::where('id', $id)
            ->where('status', 'processing')
            ->lockForUpdate()
            ->firstOrFail();

        // WALLET IS OPTIONAL
        $wallet = null;

        if (!empty($withdrawal->wallet_id)) {
            $wallet = Wallet::where('id', $withdrawal->wallet_id)
                ->lockForUpdate()
                ->first(); // ❗ NO firstOrFail
        }

        // ===============================
        // FAILED / CANCELLED → rollback
        // ===============================
        if (in_array($data['status'], ['failed', 'cancelled'], true)) {
            if ($wallet) {
                $wallet->increment('available_cents', $withdrawal->diamonds_amount);
                $wallet->decrement('reserved_cents', $withdrawal->diamonds_amount);
            }
        }

        // ===============================
        // SUCCESSFUL → finalize
        // ===============================
        if ($data['status'] === 'successful') {
            if ($wallet) {
                $wallet->decrement('reserved_cents', $withdrawal->diamonds_amount);
            }
        }

        $withdrawal->update([
            'status'       => $data['status'],
            'reference'    => $data['reference'] ?? $withdrawal->reference,
            'notes'        => $data['notes'] ?? $withdrawal->notes,
            'processed_at' => now(),
        ]);

        AuditLogger::record(
            'admin_withdrawal_' . $data['status'],
            'offline_withdrawal',
            (string) $withdrawal->id,
            [
                'wallet_id' => $withdrawal->wallet_id,
                'diamonds'  => $withdrawal->diamonds_amount,
                'usd'       => $withdrawal->payout_cents / 100,
            ]
        );

        return response()->json([
            'message'    => 'Withdrawal updated successfully.',
            'withdrawal' => $withdrawal->fresh(),
        ]);
    });
}
}
