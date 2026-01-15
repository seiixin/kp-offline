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

class OfflineWithdrawalController extends Controller
{
    /* =====================================================
     | CONFIG
     ===================================================== */
    private const DIAMONDS_PER_USD = 11200; // client rule
    private const WEEKLY_LIMIT_DAYS = 7;

    /* =====================================================
     | HELPERS
     ===================================================== */

    private function resolveAgentId(): int
    {
        $user = auth()->user();
        if (!$user) return 0;

        if (isset($user->agent_id) && (int) $user->agent_id > 0) {
            return (int) $user->agent_id;
        }

        return (int) $user->id;
    }

    private function lockAgentWallet(int $agentId): ?Wallet
    {
        return Wallet::query()
            ->where('owner_type', 'agent')
            ->where('owner_id', $agentId)
            ->lockForUpdate()
            ->first();
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

    $per = $validated['per'] ?? 20;

    /* ---------------- BASE QUERY ---------------- */

    $query = OfflineWithdrawal::query()
        ->where('agent_user_id', $user->id)
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

    /* ---------------- PAGINATE ---------------- */

    $page = $query->paginate($per);

    /* ---------------- COLLECT MONGO IDS ---------------- */

    $mongoIds = collect($page->items())
        ->pluck('mongo_user_id')
        ->filter()
        ->unique()
        ->values();

    /* ---------------- FETCH USERS FROM MONGO ---------------- */

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

    /* ---------------- ENRICH ROWS ---------------- */

    $rows = collect($page->items())->map(function ($row) use ($playersByMongoId) {
        return array_merge(
            $row->toArray(),
            [
                'player' => $playersByMongoId[$row->mongo_user_id] ?? null,
            ]
        );
    });

    /* ---------------- RESPONSE ---------------- */

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
     | Agent salary withdrawal (DIAMONDS → CASH)
     ===================================================== */
    public function store(
        StoreOfflineWithdrawalRequest $request,
        MongoEconomyService $mongoEconomy
    ) {
        $user = $request->user();
        $agentId = $this->resolveAgentId();
        $data = $request->validated();

        $diamonds = (int) $data['diamonds_amount'];
        $mongoUserId = $data['mongo_user_id'];

        /* ---------------- WEEKLY LIMIT ---------------- */
        $recent = OfflineWithdrawal::query()
            ->where('agent_user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(self::WEEKLY_LIMIT_DAYS))
            ->whereIn('status', [
                OfflineWithdrawal::STATUS_PROCESSING,
                OfflineWithdrawal::STATUS_SUCCESSFUL,
            ])
            ->exists();

        if ($recent) {
            return response()->json([
                'message' => 'Withdrawal allowed only once per week.',
            ], 422);
        }

        /* ---------------- CONVERSION ---------------- */
        $payoutCents = (int) floor(
            ($diamonds / self::DIAMONDS_PER_USD) * 100
        );

        if ($payoutCents <= 0) {
            return response()->json([
                'message' => 'Diamonds amount too low for withdrawal.',
            ], 422);
        }

        $idempotencyKey = (string) Str::uuid();

        return DB::transaction(function () use (
            $user,
            $agentId,
            $mongoEconomy,
            $mongoUserId,
            $diamonds,
            $payoutCents,
            $idempotencyKey
        ) {

            /* ---------------- LOCK AGENT WALLET ---------------- */
            $wallet = $this->lockAgentWallet($agentId);

            if (!$wallet) {
                return response()->json([
                    'message' => 'Agent wallet not found.',
                ], 422);
            }

            /* ---------------- CREATE INTENT ---------------- */
            $withdrawal = OfflineWithdrawal::create([
                'agent_user_id'   => $user->id,
                'mongo_user_id'   => $mongoUserId,
                'diamonds_amount' => $diamonds,
                'payout_cents'    => $payoutCents,
                'currency'        => 'USD',
                'status'          => OfflineWithdrawal::STATUS_PROCESSING,
                'idempotency_key' => $idempotencyKey,
            ]);

            /* ---------------- MONGO DEBIT ---------------- */
            $mongoResult = $mongoEconomy->debitDiamonds(
                $mongoUserId,
                $diamonds,
                $idempotencyKey,
                [
                    'source' => 'agent_salary_withdrawal',
                    'withdrawal_id' => $withdrawal->id,
                ]
            );

            /* ---------------- LEDGER (AGENT WALLET) ---------------- */
            LedgerEntry::create([
                'wallet_id'    => $wallet->id,
                'event_type'   => LedgerEntry::EVENT_OFFLINE_WITHDRAWAL,
                'event_id'     => $withdrawal->id,
                'direction'    => LedgerEntry::DIR_DEBIT,
                'amount_cents' => $payoutCents,
                'currency'     => 'USD',
                'meta' => [
                    'mongo_user_id' => $mongoUserId,
                    'diamonds'      => $diamonds,
                    'mongo_txn_ref' => $mongoResult['transactionRef'] ?? null,
                ],
            ]);

            /* ---------------- FINALIZE ---------------- */
            $withdrawal->update([
                'status'        => OfflineWithdrawal::STATUS_SUCCESSFUL,
                'mongo_txn_ref' => $mongoResult['transactionRef'] ?? null,
            ]);

            AuditLogger::record(
                'agent_withdrawal_success',
                'offline_withdrawal',
                (string) $withdrawal->id,
                [
                    'agent_id'   => $agentId,
                    'diamonds'   => $diamonds,
                    'usd_cents'  => $payoutCents,
                ]
            );

            return response()->json([
                'message'    => 'Withdrawal submitted successfully.',
                'withdrawal' => $withdrawal->fresh(),
            ]);
        });
    }

    /* =====================================================
     | PUT /agent/withdrawals/{id}
     | Metadata only
     ===================================================== */
    public function update(Request $request, string $id)
    {
        $user = $request->user();

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $withdrawal = OfflineWithdrawal::query()
            ->where('id', $id)
            ->where('agent_user_id', $user->id)
            ->firstOrFail();

        // HARD RULE: NEVER TOUCH AMOUNTS
        $withdrawal->update($validated);

        AuditLogger::record(
            'agent_withdrawal_meta_updated',
            'offline_withdrawal',
            (string) $withdrawal->id,
            $validated
        );

        return response()->json([
            'message'    => 'Withdrawal updated.',
            'withdrawal' => $withdrawal->fresh(),
        ]);
    }

    /* =====================================================
     | DELETE /agent/withdrawals/{id}
     | Cancel (soft)
     ===================================================== */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        $withdrawal = OfflineWithdrawal::query()
            ->where('id', $id)
            ->where('agent_user_id', $user->id)
            ->firstOrFail();

        if ($withdrawal->status !== OfflineWithdrawal::STATUS_PROCESSING) {
            return response()->json([
                'message' => 'Only processing withdrawals can be cancelled.',
            ], 422);
        }

        // IMPORTANT: diamonds already burned → no refund
        $withdrawal->update([
            'status' => OfflineWithdrawal::STATUS_CANCELLED,
            'error_payload' => [
                'reason' => 'Cancelled by agent',
            ],
        ]);

        AuditLogger::record(
            'agent_withdrawal_cancelled',
            'offline_withdrawal',
            (string) $withdrawal->id,
            []
        );

        return response()->json([
            'message'    => 'Withdrawal cancelled.',
            'withdrawal' => $withdrawal->fresh(),
        ]);
    }
}
