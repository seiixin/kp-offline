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
    private const DIAMONDS_PER_USD = 11200;
    private const MIN_DIAMONDS     = 112000; // $10
    private const CURRENCY         = 'PHP';

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
     | LIST + PLAYER NAME ENRICHMENT (FIXED)
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

        /* ===============================
         | FETCH PLAYER NAMES (RESTORED)
         =============================== */
        $mongoIds = collect($page->items())
            ->pluck('mongo_user_id')
            ->filter()
            ->unique()
            ->values();

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

        $rows = collect($page->items())->map(function ($row) use ($players) {
            return array_merge(
                $row->toArray(),
                ['player' => $players[$row->mongo_user_id] ?? null]
            );
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
     | PLAYER WITHDRAWAL (USES AGENT CASH)
     ===================================================== */
public function store(
    StoreOfflineWithdrawalRequest $request,
    MongoEconomyService $mongoEconomy
): JsonResponse {
    $agentId = $this->resolveAgentId();
    $data    = $request->validated();

    $mongoUserId = $data['mongo_user_id'];
    $diamonds    = (int) $data['diamonds_amount'];

    /* ===============================
     | MINIMUM RULE
     =============================== */
    if ($diamonds < self::MIN_DIAMONDS) {
        return response()->json([
            'message' => 'Minimum withdrawal is $10.',
        ], 422);
    }

    /* ===============================
     | SERVER-AUTHORITATIVE CONVERSION
     =============================== */
    $usd         = $diamonds / self::DIAMONDS_PER_USD;
    $payoutCents = (int) round($usd * 100 * 56); // USD → PHP (server rate)

    try {
        $withdrawal = DB::transaction(function () use (
            $agentId,
            $mongoUserId,
            $diamonds,
            $payoutCents,
            $mongoEconomy,
            $data
        ) {
            /* ===============================
             | LOCK AGENT CASH WALLET (PHP)
             =============================== */
            $wallet = Wallet::where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->whereRaw('UPPER(asset) = ?', ['PHP'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($wallet->available_cents < $payoutCents) {
                throw new \RuntimeException('Insufficient agent cash liquidity.');
            }

            /* ===============================
             | RESERVE AGENT CASH
             =============================== */
            $wallet->decrement('available_cents', $payoutCents);
            $wallet->increment('reserved_cents', $payoutCents);

            /* ===============================
             | RESERVE PLAYER DIAMONDS (MONGO)
             =============================== */
            $mongoEconomy->reserveDiamonds(
                $mongoUserId,
                $diamonds
            );

            /* ===============================
             | CREATE WITHDRAWAL RECORD
             =============================== */
            $withdrawal = OfflineWithdrawal::create([
                'agent_user_id'   => $agentId,
                'mongo_user_id'   => $mongoUserId,
                'diamonds_amount' => $diamonds,
                'payout_cents'    => $payoutCents,
                'currency'        => self::CURRENCY,
                'status'          => 'processing',
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
            ]);

            /* ===============================
             | LEDGER ENTRY (CASH)
             =============================== */
            LedgerEntry::create([
                'wallet_id'    => $wallet->id,
                'event_type'   => LedgerEntry::EVENT_OFFLINE_WITHDRAWAL,
                'event_id'     => $withdrawal->id,
                'direction'    => LedgerEntry::DIR_DEBIT,
                'amount_cents' => $payoutCents,
                'meta'         => [
                    'unit'     => 'PHP',
                    'diamonds' => $diamonds,
                ],
            ]);

            AuditLogger::record(
                'player_withdrawal_requested',
                'offline_withdrawal',
                (string) $withdrawal->id,
                [
                    'mongo_user_id' => $mongoUserId,
                    'diamonds'      => $diamonds,
                    'php'           => $payoutCents / 100,
                ]
            );

            return $withdrawal;
        });
    } catch (\RuntimeException $e) {
        return response()->json([
            'message' => $e->getMessage(),
        ], 422);
    }

    return response()->json([
        'message'    => 'Withdrawal request submitted.',
        'withdrawal' => $withdrawal,
    ]);
}
    /* =====================================================
     | PUT /agent/withdrawals/{id}
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
            'player_withdrawal_updated',
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
     | CANCEL → RELEASE CASH + PLAYER DIAMONDS
     ===================================================== */
    public function destroy(
        Request $request,
        int $id,
        MongoEconomyService $mongoEconomy
    ): JsonResponse {
        $agentId = $this->resolveAgentId();

        return DB::transaction(function () use ($agentId, $id, $mongoEconomy) {
            $withdrawal = OfflineWithdrawal::where('id', $id)
                ->where('agent_user_id', $agentId)
                ->where('status', 'processing')
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::where('owner_type', 'agent')
                ->where('owner_id', $agentId)
                ->whereRaw('UPPER(asset) = ?', ['PHP'])
                ->lockForUpdate()
                ->firstOrFail();

            // Release agent cash
            $wallet->increment('available_cents', $withdrawal->payout_cents);
            $wallet->decrement('reserved_cents', $withdrawal->payout_cents);

            // Release player diamonds
            $mongoEconomy->releaseReservedDiamonds(
                $withdrawal->mongo_user_id,
                $withdrawal->diamonds_amount
            );

            $withdrawal->update([
                'status' => 'cancelled',
            ]);

            AuditLogger::record(
                'player_withdrawal_cancelled',
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
