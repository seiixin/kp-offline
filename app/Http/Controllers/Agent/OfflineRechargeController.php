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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfflineRechargeController extends Controller
{
    /**
     * GET /agent/recharges/list
     * Returns paginated JSON rows for the Agent Recharges page.
     */
    public function list(): JsonResponse
    {
        $agentId = $this->resolveAgentId();

        $q = trim((string) request('q', ''));
        $status = trim((string) request('status', ''));
        $perPage = (int) request('per_page', 15);
        $perPage = max(5, min(100, $perPage));

        $query = OfflineRecharge::query();

        // agent scoping
        if (Schema::hasColumn('offline_recharges', 'agent_id')) {
            $query->where('agent_id', $agentId);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                foreach (['mongo_user_id', 'reference', 'method', 'idempotency_key', 'mobile_txn_ref'] as $col) {
                    if (Schema::hasColumn('offline_recharges', $col)) {
                        $sub->orWhere($col, 'like', '%' . $q . '%');
                    }
                }
                if (ctype_digit($q) && Schema::hasColumn('offline_recharges', 'id')) {
                    $sub->orWhere('id', (int) $q);
                }
            });
        }

        if ($status !== '' && Schema::hasColumn('offline_recharges', 'status')) {
            $query->where('status', $status);
        }

        $query->orderByDesc('id');

        $rows = $query->paginate($perPage)->through(function (OfflineRecharge $r) {
            return [
                'id' => $r->id,
                'mongo_user_id' => $r->mongo_user_id,
                'coins_amount' => (int) $r->coins_amount,
                'amount_usd_cents' => (int) $r->amount_usd_cents,
                'method' => $r->method,
                'reference' => $r->reference,
                'status' => $r->status,
                'idempotency_key' => $r->idempotency_key,
                'mobile_txn_ref' => $r->mobile_txn_ref,
                'proof_url' => $r->proof_url,
                'created_at' => optional($r->created_at)->toISOString(),
            ];
        });

        return response()->json([
            'filters' => [
                'q' => $q,
                'status' => $status,
                'per_page' => $perPage,
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * POST /agent/recharges
     * Creates MySQL intent -> Mongo write -> Finalize MySQL (wallet + ledger + audit)
     */
    public function store(StoreOfflineRechargeRequest $request): JsonResponse
    {
        $actorUserId = (int) Auth::id();
        $agentId = $this->resolveAgentId();

        $idempotencyKey = (string) ($request->input('idempotency_key')
            ?: $request->header('Idempotency-Key')
            ?: (string) Str::uuid());

        $mongoUserId = (string) $request->input('mongo_user_id');
        $coinsAmount = (int) $request->input('coins_amount');
        $method = (string) $request->input('method');
        $reference = (string) ($request->input('reference') ?? '');
        $proofUrl = (string) ($request->input('proof_url') ?? '');
        $notes = (string) ($request->input('notes') ?? '');

        // MySQL idempotency: if already completed, return "already processed"
        $existing = OfflineRecharge::query()
            ->where('agent_id', $agentId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing && in_array((string) $existing->status, ['completed', 'successful'], true)) {
            return response()->json([
                'ok' => true,
                'already_processed' => true,
                'offline_recharge_id' => $existing->id,
                'idempotency_key' => $idempotencyKey,
            ]);
        }

        // Pricing rule (USD cents): amount_usd_cents = coins_amount * price_per_coin_usd_cents
        $pricePerCoinUsdCents = (int) config('services.offline_recharge.price_per_coin_usd_cents', 0);
        if ($pricePerCoinUsdCents <= 0) {
            $pricePerCoinUsdCents = (int) env('OFFLINE_RECHARGE_PRICE_PER_COIN_USD_CENTS', 1);
        }
        $amountUsdCents = $coinsAmount * $pricePerCoinUsdCents;

        // Step 1: Create / reuse MySQL intent (processing)
        $intent = $existing ?: new OfflineRecharge();

        DB::beginTransaction();
        try {
            if (!$intent->exists) {
                $intent->fill([
                    'agent_id' => $agentId,
                    'mongo_user_id' => $mongoUserId,
                    'amount_usd_cents' => $amountUsdCents,
                    'coins_amount' => $coinsAmount,
                    'method' => $method,
                    'reference' => $reference !== '' ? $reference : null,
                    'status' => 'processing',
                    'idempotency_key' => $idempotencyKey,
                    'proof_url' => $proofUrl !== '' ? $proofUrl : null,
                    'mobile_txn_ref' => null,
                    'meta_json' => [
                        'pricing' => [
                            'price_per_coin_usd_cents' => $pricePerCoinUsdCents,
                            'amount_usd_cents' => $amountUsdCents,
                        ],
                        'notes' => $notes !== '' ? $notes : null,
                    ],
                ]);
                $intent->save();
            } else {
                // keep intent up to date (safe fields only)
                $intent->method = $method;
                $intent->reference = $reference !== '' ? $reference : $intent->reference;
                $intent->proof_url = $proofUrl !== '' ? $proofUrl : $intent->proof_url;
                $intent->status = $intent->status ?: 'processing';
                $intent->save();
            }

            // Pre-check wallet before Mongo, to avoid crediting if agent has insufficient funds
            $wallet = $this->lockAgentWallet($agentId);
            if (!$wallet) {
                $this->markFailed($intent, $actorUserId, 'wallet_missing', [
                    'agent_id' => $agentId,
                ]);
                DB::commit();

                return response()->json([
                    'ok' => false,
                    'error' => 'Agent wallet not found.',
                    'offline_recharge_id' => $intent->id,
                ], 422);
            }

            if ((int) $wallet->available_cents < $amountUsdCents) {
                $this->markFailed($intent, $actorUserId, 'insufficient_funds', [
                    'available_cents' => (int) $wallet->available_cents,
                    'required_cents' => $amountUsdCents,
                ]);
                DB::commit();

                return response()->json([
                    'ok' => false,
                    'error' => 'Insufficient agent wallet balance.',
                    'offline_recharge_id' => $intent->id,
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // best-effort audit (no intent id if it failed before save)
            $this->audit($actorUserId, 'offline_recharge.failed_intent', 'OfflineRecharge', $intent->id ?? 0, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Step 2: Mongo economy write (idempotent by transactionRef)
        try {
            // Instantiate inside try so config/extension errors are catchable (avoids raw 500 on DI construction)
            $mongo = new \App\Services\MongoEconomyService();

            $mongoResult = $mongo->creditCoins([
                'mongo_user_id' => $mongoUserId,
                'coins_amount' => $coinsAmount,
                'idempotency_key' => $idempotencyKey,
                'source' => 'offline_agent',
                'meta' => [
                    'agent_id' => $agentId,
                    'offline_recharge_id' => $intent->id,
                ],
            ]);
        } catch (\Throwable $e) {
            // Step 4: mark failed (no wallet deduction / ledger entry)
            $this->markFailed($intent, $actorUserId, 'mongo_write_failed', [
                'error' => $e->getMessage(),
            ]);

            $msg = config('app.debug') ? $e->getMessage() : 'Mongo economy write failed.';

            // If it's a config-style error, return 422 for faster feedback
            if (str_contains($e->getMessage(), 'Mongo configuration missing')) {
                return response()->json([
                    'ok' => false,
                    'error' => $msg,
                    'offline_recharge_id' => $intent->id,
                    'idempotency_key' => $idempotencyKey,
                ], 422);
            }

            return response()->json([
                'ok' => false,
                'error' => $msg,
                'offline_recharge_id' => $intent->id,
                'idempotency_key' => $idempotencyKey,
            ], 500);
        }

        // Step 3: Finalize MySQL (wallet deduction + ledger + status + audit)
        try {
            DB::beginTransaction();

            /** @var OfflineRecharge $lockedIntent */
            $lockedIntent = OfflineRecharge::query()
                ->where('id', $intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array((string) $lockedIntent->status, ['completed', 'successful'], true)) {
                DB::commit();
                return response()->json([
                    'ok' => true,
                    'already_processed' => true,
                    'offline_recharge_id' => $lockedIntent->id,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            $wallet = $this->lockAgentWallet($agentId);
            if (!$wallet) {
                $this->markFailed($lockedIntent, $actorUserId, 'wallet_missing_finalize', []);
                DB::commit();

                return response()->json([
                    'ok' => false,
                    'error' => 'Agent wallet not found during finalize.',
                    'offline_recharge_id' => $lockedIntent->id,
                ], 422);
            }

            // Idempotent wallet deduction: if ledger already exists, skip deduction
            $alreadyLedgered = LedgerEntry::query()
                ->where('event_type', 'offline_recharge')
                ->where('event_id', $lockedIntent->id)
                ->exists();

            if (!$alreadyLedgered) {
                if ((int) $wallet->available_cents < $amountUsdCents) {
                    $this->markFailed($lockedIntent, $actorUserId, 'insufficient_funds_finalize', [
                        'available_cents' => (int) $wallet->available_cents,
                        'required_cents' => $amountUsdCents,
                    ]);
                    DB::commit();

                    return response()->json([
                        'ok' => false,
                        'error' => 'Insufficient agent wallet balance during finalize.',
                        'offline_recharge_id' => $lockedIntent->id,
                    ], 422);
                }

                $wallet->available_cents = (int) $wallet->available_cents - $amountUsdCents;
                $wallet->save();

                LedgerEntry::create([
                    'wallet_id' => $wallet->id,
                    'direction' => '-',
                    'amount_cents' => $amountUsdCents,
                    'event_type' => 'offline_recharge',
                    'event_id' => $lockedIntent->id,
                    'meta_json' => [
                        'mongo_user_id' => $mongoUserId,
                        'coins_amount' => $coinsAmount,
                        'idempotency_key' => $idempotencyKey,
                        'mongo' => $mongoResult,
                    ],
                ]);
            }

            $lockedIntent->status = 'completed';
            $lockedIntent->mobile_txn_ref = (string) ($mongoResult['transactionRef'] ?? $idempotencyKey);
            $lockedIntent->meta_json = array_merge((array) ($lockedIntent->meta_json ?? []), [
                'mongo' => $mongoResult,
                'finalized_at' => now()->toISOString(),
            ]);
            $lockedIntent->save();

            $this->audit($actorUserId, 'offline_recharge.completed', 'OfflineRecharge', $lockedIntent->id, [
                'mongo_user_id' => $mongoUserId,
                'coins_amount' => $coinsAmount,
                'amount_usd_cents' => $amountUsdCents,
                'idempotency_key' => $idempotencyKey,
            ]);

            DB::commit();

            return response()->json([
                'ok' => true,
                'offline_recharge_id' => $lockedIntent->id,
                'idempotency_key' => $idempotencyKey,
                'already_processed' => (bool) ($mongoResult['already_processed'] ?? false),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->markFailed($intent, $actorUserId, 'finalize_failed', [
                'error' => $e->getMessage(),
                'mongo' => $mongoResult ?? null,
            ]);

            return response()->json([
                'ok' => false,
                'error' => (config('app.debug') ? $e->getMessage() : 'Finalize failed.'),
                'offline_recharge_id' => $intent->id,
                'idempotency_key' => $idempotencyKey,
            ], 500);
        }
    }

    private function resolveAgentId(): int
    {
        $user = Auth::user();
        if (!$user) return 0;

        // If user has agent_id column, prefer it
        if (isset($user->agent_id) && (int) $user->agent_id > 0) {
            return (int) $user->agent_id;
        }

        // If Agent model exists and has user_id relationship, use it
        $agentModel = 'App\\Models\\Agent';
        if (class_exists($agentModel)) {
            try {
                $agent = $agentModel::query()->where('user_id', (int) $user->id)->first();
                if ($agent) return (int) $agent->id;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Fallback: assume agent id matches user id
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

    private function markFailed(OfflineRecharge $intent, int $actorUserId, string $reason, array $detail): void
    {
        $intent->status = 'failed';
        $intent->meta_json = array_merge((array) ($intent->meta_json ?? []), [
            'failed_at' => now()->toISOString(),
            'fail_reason' => $reason,
            'error_detail' => $detail,
        ]);
        $intent->save();

        $this->audit($actorUserId, 'offline_recharge.failed', 'OfflineRecharge', $intent->id, [
            'reason' => $reason,
            'detail' => $detail,
            'idempotency_key' => $intent->idempotency_key,
        ]);
    }

    private function audit(int $actorUserId, string $action, string $entityType, int $entityId, array $detailJson): void
    {
        if (!class_exists(AuditLog::class) || !Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'detail_json' => $detailJson,
        ]);
    }
}
