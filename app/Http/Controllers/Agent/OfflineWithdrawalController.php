<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreOfflineWithdrawalRequest;
use App\Models\LedgerEntry;
use App\Models\OfflineWithdrawal;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\MongoEconomyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class OfflineWithdrawalController extends Controller
{
    /* =====================================================
     | CONFIG
     ===================================================== */
    private const DIAMONDS_PER_USD = 11200; // client rule
    private const WEEKLY_LIMIT_DAYS = 7;
    private const MIN_DIAMONDS = 112000; // $10 worth of diamonds

    /* =====================================================
     | HELPERS
     ===================================================== */

    /**
     * Resolves the agent ID based on the current logged-in user
     */
    private function resolveAgentId(): int
    {
        $user = auth()->user();
        return (int) ($user->agent_id ?? $user->id); // Get agent_id or use the user id if it's the same
    }

    /**
     * Locks the agent's wallet for update (to prevent race conditions)
     */
/**
 * Lock and fetch agent's wallet, or create one if it doesn't exist
 */
private function lockAgentWallet(int $agentId): Wallet
{
    // Attempt to find the wallet (lock it for update)
    $wallet = Wallet::where('owner_type', 'agent')
        ->where('owner_id', $agentId)
        ->where('asset', 'DIAMONDS')  // Ensure we're using the DIAMONDS wallet
        ->lockForUpdate()
        ->first();

    if (!$wallet) {
        // If wallet doesn't exist, create a new one
        $wallet = Wallet::create([
            'user_id' => $agentId,         // Assuming the agentId is the userId
            'owner_type' => 'agent',
            'owner_id' => $agentId,
            'asset' => 'DIAMONDS',
            'available_cents' => 0,        // Initial balance
            'reserved_cents' => 0,         // Reserved balance
        ]);
    }

    return $wallet;
}

    /* =====================================================
     | GET /agent/withdrawals/list
     ===================================================== */
    public function list(Request $request, MongoEconomyService $mongoEconomy)
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['nullable', 'in:processing,successful,cancelled,failed'],
            'q'      => ['nullable', 'string', 'max:120'],
            'per'    => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = OfflineWithdrawal::where('agent_user_id', $user->id)
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['q'])) {
            $q = trim($validated['q']);
            $query->where(function ($sub) use ($q) {
                $sub->where('mongo_user_id', 'like', "%{$q}%")
                    ->orWhere('user_identification', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        $page = $query->paginate($validated['per'] ?? 20);

        // Enrich data with player information
        $mongoIds = collect($page->items())->pluck('mongo_user_id')->filter()->unique()->values();
        $playersByMongoId = [];
        foreach ($mongoIds as $mongoId) {
            $player = $mongoEconomy->getUserBasicByMongoId($mongoId);
            if ($player) {
                $playersByMongoId[$mongoId] = [
                    'full_name' => $player['full_name'] ?? null,
                    'username'  => $player['username'] ?? null,
                ];
            }
        }

        // Return paginated response with enriched data
        $rows = collect($page->items())->map(function ($row) use ($playersByMongoId) {
            return array_merge($row->toArray(), [
                'player' => $playersByMongoId[$row->mongo_user_id] ?? null,
            ]);
        });

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
     | AGENT — once per week (SUCCESSFUL only)
     ===================================================== */
public function store(StoreOfflineWithdrawalRequest $request): JsonResponse
{
    $user = $request->user();
    $agentId = $this->resolveAgentId();
    $data = $request->validated();

    $diamonds = (int) $data['diamonds_amount'];
    $mongoUserId = $data['mongo_user_id'];

    // WEEKLY LIMIT: Ensure withdrawal can only happen once per week
    $recent = OfflineWithdrawal::where('agent_user_id', $user->id)
        ->where('status', 'successful')
        ->where('created_at', '>=', now()->subDays(self::WEEKLY_LIMIT_DAYS))
        ->exists();

    if ($recent) {
        return response()->json([
            'message' => 'Withdrawal allowed only once per week.',
        ], 422);
    }

    if ($diamonds < self::MIN_DIAMONDS) {
        return response()->json([
            'message' => 'Minimum withdrawal is $10.',
        ], 422);
    }

    // Convert diamonds to USD cents
    $payoutCents = (int) floor(($diamonds / self::DIAMONDS_PER_USD) * 100);

    // Lock the agent wallet and proceed with the transaction
    return DB::transaction(function () use ($agentId, $diamonds, $payoutCents, $mongoUserId) {
        $wallet = $this->lockAgentWallet($agentId); // Lock agent wallet for update

        // Check if the agent has sufficient diamonds in their available balance
        if ($wallet->available_cents < $diamonds) {
            return response()->json([
                'message' => 'Insufficient agent commission balance.',
            ], 422);
        }

        // Reserve the diamonds (moving to reserved_cents)
        $wallet->decrement('available_cents', $diamonds);
        $wallet->increment('reserved_cents', $diamonds);

        // Create the withdrawal record
        $withdrawal = OfflineWithdrawal::create([
            'agent_user_id'   => $agentId,
            'mongo_user_id'   => $mongoUserId,
            'diamonds_amount' => $diamonds,
            'payout_cents'    => $payoutCents,
            'currency'        => 'USD',
            'status'          => 'processing',  // Initially set to 'processing'
            'idempotency_key' => (string) Str::uuid(),
        ]);

        // Create ledger entry for the withdrawal
LedgerEntry::create([
    'wallet_id'     => $wallet->id,
    'event_type'    => LedgerEntry::EVENT_OFFLINE_WITHDRAWAL,
    'event_id'      => $withdrawal->id,
    'direction'     => LedgerEntry::DIR_DEBIT,
    'amount_cents'  => $diamonds * self::DIAMONDS_PER_USD, // <-- Adding the correct field 'amount_cents'
    'unit'          => 'DIAMONDS',
]);


        // Log the action
        AuditLogger::record(
            'agent_withdrawal_requested',
            'offline_withdrawal',
            (string) $withdrawal->id,
            [
                'diamonds' => $diamonds,
                'usd'      => $payoutCents / 100,
            ]
        );

        // Finalize the withdrawal status after all actions are completed (admin completes it)
        $withdrawal->update([
            'status' => 'successful',  // Change status to 'successful' after completion
            'mongo_txn_ref' => (string) Str::uuid(),  // Add a transaction reference
        ]);

        return response()->json([
            'message'    => 'Withdrawal request submitted successfully.',
            'withdrawal' => $withdrawal->fresh(),
        ]);
    });
}


    /* =====================================================
     | PUT /agent/withdrawals/{id}
     ===================================================== */
    public function update(Request $request, string $id)
    {
        $withdrawal = OfflineWithdrawal::where('id', $id)
            ->where('agent_user_id', $request->user()->id)
            ->where('status', 'processing')
            ->firstOrFail();

        $withdrawal->update(
            $request->validate([
                'reference' => ['nullable', 'string', 'max:120'],
                'notes'     => ['nullable', 'string', 'max:500'],
            ])
        );

        return response()->json([
            'message' => 'Withdrawal updated.',
            'withdrawal' => $withdrawal->fresh(),
        ]);
    }

    /* =====================================================
     | DELETE /agent/withdrawals/{id}
     ===================================================== */
    public function destroy(Request $request, string $id)
    {
        return DB::transaction(function () use ($request, $id) {

            $withdrawal = OfflineWithdrawal::where('id', $id)
                ->where('agent_user_id', $request->user()->id)
                ->where('status', 'processing')
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::where('owner_type', 'agent')
                ->where('owner_id', $withdrawal->agent_user_id)
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
