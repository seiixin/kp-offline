<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreOfflineWithdrawalRequest;
use App\Models\LedgerEntry;
use App\Models\OfflineWithdrawal;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\MongoEconomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfflineWithdrawalController extends Controller
{
    /* =====================================================
     | CONFIG
     ===================================================== */
    private const DIAMONDS_PER_USD  = 11200;
    private const WEEKLY_LIMIT_DAYS = 7;
    private const MIN_DIAMONDS      = 112000; // $10

    /* =====================================================
     | HELPERS
     ===================================================== */
     
    protected function resolveAgentId(): int
    {
        $user = auth()->user();
        return (int) ($user->agent_id ?? $user->id);
    }

    /* =====================================================
     | GET /agent/withdrawals
     ===================================================== */
    public function list(Request $request, MongoEconomyService $mongoEconomy): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $validated = $request->validate([
            'status' => ['nullable', 'in:processing,successful,cancelled,failed'],
            'q'      => ['nullable', 'string', 'max:120'],
            'per'    => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = OfflineWithdrawal::where('agent_user_id', $agentId)->latest();

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

        $mongoIds = collect($page->items())
            ->pluck('mongo_user_id')
            ->filter()
            ->unique();

        $players = [];
        foreach ($mongoIds as $mongoId) {
            $p = $mongoEconomy->getUserBasicByMongoId($mongoId);
            if ($p) {
                $players[$mongoId] = [
                    'full_name' => $p['full_name'] ?? null,
                    'username'  => $p['username'] ?? null,
                ];
            }
        }

        $rows = collect($page->items())->map(fn ($row) => array_merge(
            $row->toArray(),
            ['player' => $players[$row->mongo_user_id] ?? null]
        ));

        return response()->json([
            'data' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'data'         => $rows,
            ],
        ]);
    }

    /* =====================================================
     | POST /agent/withdrawals
     | Agent submits request (PROCESSING ONLY)
     ===================================================== */
public function store(StoreOfflineWithdrawalRequest $request): JsonResponse
{
    $agentId = $this->resolveAgentId();
    $data    = $request->validated();

    $diamonds    = (int) $data['diamonds_amount'];
    $mongoUserId = $data['mongo_user_id'];
    $walletId    = (int) $data['wallet_id'];

    // ===============================
    // Weekly withdrawal limit
    // ===============================
    $recent = OfflineWithdrawal::where('agent_user_id', $agentId)
        ->where('status', 'successful')
        ->where('created_at', '>=', now()->subDays(self::WEEKLY_LIMIT_DAYS))
        ->exists();

    if ($recent) {
        return response()->json([
            'message' => 'Withdrawal allowed only once per week.',
        ], 422);
    }

    // ===============================
    // Minimum diamonds rule ($10)
    // ===============================
    if ($diamonds < self::MIN_DIAMONDS) {
        return response()->json([
            'message' => 'Minimum withdrawal is $10.',
        ], 422);
    }

    // ===============================
    // SERVER-AUTHORITATIVE PAYOUT
    // ===============================
    $payoutCents = (int) floor(
        ($diamonds / self::DIAMONDS_PER_USD) * 100
    );

    return DB::transaction(function () use (
        $agentId,
        $walletId,
        $diamonds,
        $mongoUserId,
        $payoutCents,
        $data
    ) {
        // ===============================
        // Lock agent wallet
        // ===============================
        $wallet = Wallet::where('id', $walletId)
            ->where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->whereRaw('UPPER(asset) = ?', ['DIAMONDS'])
            ->lockForUpdate()
            ->firstOrFail();

        if ($wallet->available_cents < $diamonds) {
            return response()->json([
                'message' => 'Insufficient agent commission balance.',
            ], 422);
        }

        // ===============================
        // Reserve diamonds
        // ===============================
        $wallet->decrement('available_cents', $diamonds);
        $wallet->increment('reserved_cents', $diamonds);

        // ===============================
        // Create withdrawal request
        // ===============================
        $withdrawal = OfflineWithdrawal::create([
            'wallet_id'       => $wallet->id,
            'agent_user_id'   => $agentId,
            'mongo_user_id'   => $mongoUserId,
            'diamonds_amount' => $diamonds,
            'payout_cents'    => $payoutCents,
            'currency'        => 'USD',
            'payout_method'   => $data['payout_method'] ?? null, // OPTIONAL
            'status'          => 'processing',
            'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
        ]);

        // ===============================
        // Ledger entry (DIAMONDS)
        // ===============================
        LedgerEntry::create([
            'wallet_id'    => $wallet->id,
            'event_type'   => LedgerEntry::EVENT_OFFLINE_WITHDRAWAL,
            'event_id'     => $withdrawal->id,
            'direction'    => LedgerEntry::DIR_DEBIT,
            'amount_cents' => $diamonds,
            'meta'         => [
                'unit' => 'DIAMONDS',
            ],
        ]);

        // ===============================
        // Audit log
        // ===============================
        AuditLogger::record(
            'agent_withdrawal_requested',
            'offline_withdrawal',
            (string) $withdrawal->id,
            [
                'diamonds' => $diamonds,
                'usd'      => $payoutCents / 100,
            ]
        );

        return response()->json([
            'message'    => 'Withdrawal request submitted.',
            'withdrawal' => $withdrawal,
        ]);
    });
}

    /* =====================================================
     | PUT /agent/withdrawals/{id}
     | Update reference / notes (processing only)
     ===================================================== */
    public function update(Request $request, int $id): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $withdrawal = OfflineWithdrawal::where('id', $id)
            ->where('agent_user_id', $agentId)
            ->where('status', 'processing')
            ->firstOrFail();

        $withdrawal->update(
            $request->validate([
                'reference' => ['nullable', 'string', 'max:120'],
                'notes'     => ['nullable', 'string', 'max:500'],
            ])
        );

        AuditLogger::record(
            'agent_withdrawal_updated',
            'offline_withdrawal',
            (string) $withdrawal->id,
            []
        );

        return response()->json([
            'message'    => 'Withdrawal updated.',
            'withdrawal' => $withdrawal->fresh(),
        ]);
    }

    /* =====================================================
     | DELETE /agent/withdrawals/{id}
     | Cancel (processing only)
     ===================================================== */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        return DB::transaction(function () use ($agentId, $id) {
            $withdrawal = OfflineWithdrawal::where('id', $id)
                ->where('agent_user_id', $agentId)
                ->where('status', 'processing')
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::where('id', $withdrawal->wallet_id)
                ->where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->increment('available_cents', $withdrawal->diamonds_amount);
            $wallet->decrement('reserved_cents', $withdrawal->diamonds_amount);

            $withdrawal->update([
                'status' => 'cancelled',
            ]);

            AuditLogger::record(
                'agent_withdrawal_cancelled',
                'offline_withdrawal',
                (string) $withdrawal->id,
                []
            );

            return response()->json([
                'message' => 'Withdrawal cancelled.',
            ]);
        });
    }
}
