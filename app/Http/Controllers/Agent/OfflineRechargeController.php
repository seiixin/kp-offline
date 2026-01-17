<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreOfflineRechargeRequest;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\OfflineRecharge;
use App\Models\Wallet;
use App\Services\MongoEconomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class OfflineRechargeController extends Controller
{
    private const CASH_CURRENCY = 'PHP';

    // ===== ECONOMY CONSTANTS (CANONICAL) =====
    private const COINS_PER_USD   = 14000; // 14,000 coins = $1
    private const USD_TO_PHP_RATE = 56;    // $1 = ₱56

    /* =====================================================
     | LIST
     ===================================================== */
    public function list(Request $request, MongoEconomyService $mongoEconomy): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $validated = $request->validate([
            'q'      => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:processing,completed,failed'],
            'per'    => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = OfflineRecharge::where('agent_id', $agentId)->latest();

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

        $rows = collect($page->items())->map(fn ($r) => array_merge(
            $r->toArray(),
            ['player' => $players[$r->mongo_user_id] ?? null]
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
     | STORE
     | OFFLINE RECHARGE
     | Agent CASH (soft funds) ↓ → Player COINS ↑
     ===================================================== */
    public function store(
        StoreOfflineRechargeRequest $request,
        MongoEconomyService $mongoEconomy
    ): JsonResponse {
        $agentId = $this->resolveAgentId();
        $actorId = (int) Auth::id();
        $data    = $request->validated();

        /* ===============================
        | INPUT (FROM REQUEST)
        =============================== */
        $coins          = (int) $data['coins_amount'];
        $clientUsdCents = (int) $data['amount_usd_cents']; // REQUIRED, FROM FRONTEND
        $mongoUserId    = $data['mongo_user_id'];
        $method         = $data['method'];
        $reference      = $data['reference'] ?? null;
        $proofUrl       = $data['proof_url'] ?? null;
        $idemKey        = $data['idempotency_key'] ?? (string) Str::uuid();

        if ($coins <= 0) {
            return response()->json([
                'message' => 'Invalid coin amount.',
            ], 422);
        }

        try {
            $recharge = DB::transaction(function () use (
                $agentId,
                $actorId,
                $coins,
                $clientUsdCents,
                $mongoUserId,
                $method,
                $reference,
                $proofUrl,
                $idemKey,
                $mongoEconomy
            ) {
                /* ===============================
                | 0) IDEMPOTENCY
                =============================== */
                if ($existing = OfflineRecharge::where('idempotency_key', $idemKey)->first()) {
                    return $existing;
                }

                /* ===============================
                | 1) BACKEND CANONICAL CONVERSION
                =============================== */
                $computedUsdCents = (int) round(
                    ($coins / self::COINS_PER_USD) * 100
                );

                if ($computedUsdCents <= 0) {
                    throw new RuntimeException('Computed USD amount invalid.');
                }

                // 🔒 CRITICAL: CLIENT vs SERVER CONSISTENCY CHECK
                // allow ±1 cent tolerance for rounding
                if (abs($computedUsdCents - $clientUsdCents) > 1) {
                    throw new RuntimeException('USD amount mismatch with coin value.');
                }

                // server value is authoritative
                $usdCents = $computedUsdCents;

                $phpCents = (int) round(
                    $usdCents * self::USD_TO_PHP_RATE
                );

                /* ===============================
                | 2) LOCK AGENT CASH WALLET (PHP)
                =============================== */
                $wallet = Wallet::where('owner_type', 'agent')
                    ->where('owner_id', $agentId)
                    ->whereRaw('UPPER(asset) = ?', [self::CASH_CURRENCY])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($wallet->available_cents < $phpCents) {
                    throw new RuntimeException('Insufficient agent cash balance.');
                }

                /* ===============================
                | 3) CREATE RECHARGE (PROCESSING)
                =============================== */
                $recharge = OfflineRecharge::create([
                    'agent_id'         => $agentId,
                    'mongo_user_id'    => $mongoUserId,
                    'coins_amount'     => $coins,
                    'amount_usd_cents' => $usdCents,
                    'currency'         => self::CASH_CURRENCY,
                    'method'           => $method,
                    'reference'        => $reference,
                    'proof_url'        => $proofUrl,
                    'status'           => 'processing',
                    'idempotency_key'  => $idemKey,
                ]);

                /* ===============================
                | 4) DEBIT AGENT CASH (PHP)
                =============================== */
                $wallet->decrement('available_cents', $phpCents);

                /* ===============================
                | 5) LEDGER ENTRY (CASH ONLY)
                =============================== */
                LedgerEntry::create([
                    'wallet_id'    => $wallet->id,
                    'event_type'   => LedgerEntry::EVENT_OFFLINE_RECHARGE,
                    'event_id'     => $recharge->id,
                    'direction'    => LedgerEntry::DIR_DEBIT,
                    'amount_cents' => $phpCents,
                    'currency'     => self::CASH_CURRENCY,
                    'meta'         => [
                        'mongo_user_id' => $mongoUserId,
                        'coins'         => $coins,
                        'usd_cents'     => $usdCents,
                    ],
                ]);

                /* ===============================
                | 6) CREDIT PLAYER COINS (MONGO)
                =============================== */
                $mongoEconomy->creditCoins([
                    'mongo_user_id'   => $mongoUserId,
                    'coins_amount'    => $coins,
                    'idempotency_key' => $idemKey,
                    'source'          => 'offline_agent_recharge',
                    'meta'            => [
                        'agent_id'            => $agentId,
                        'offline_recharge_id' => $recharge->id,
                        'usd_cents'           => $usdCents,
                    ],
                ]);

                /* ===============================
                | 7) FINALIZE
                =============================== */
                $recharge->update(['status' => 'completed']);

                $this->audit(
                    $actorId,
                    'offline_recharge.completed',
                    $recharge->id,
                    [
                        'coins' => $coins,
                        'usd'   => $usdCents / 100,
                        'php'   => $phpCents / 100,
                    ]
                );

                return $recharge;
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message'  => 'Offline recharge completed.',
            'recharge' => $recharge,
        ]);
    }
    /* =====================================================
     | HELPERS
     ===================================================== */
    private function resolveAgentId(): int
    {
        $user = Auth::user();
        return (int) ($user->agent_id ?? $user->id);
    }

    private function audit(int $actorId, string $action, int $entityId, array $meta): void
    {
        if (!class_exists(AuditLog::class) || !Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'actor_user_id' => $actorId,
            'action'        => $action,
            'entity_type'   => 'offline_recharge',
            'entity_id'     => $entityId,
            'detail_json'   => $meta,
        ]);
    }
}
