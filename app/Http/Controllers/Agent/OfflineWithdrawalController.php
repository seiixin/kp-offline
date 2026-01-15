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
    /**
     * GET /agent/withdrawals/list
     * List withdrawals for the authenticated agent with basic filters.
     */
    public function list(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['nullable', 'in:processing,successful,failed'],
            'q'      => ['nullable', 'string', 'max:120'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
            'per'    => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $per = $validated['per'] ?? 20;

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
                    ->orWhere('idempotency_key', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        if (!empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        return response()->json([
            'data' => $query->paginate($per),
        ]);
    }

    /**
     * POST /agent/withdrawals
     *
     * Flow:
     * 1) Idempotency check
     * 2) Lock (or create) agent wallet row
     * 3) Resolve Mongo user by UserIdentification (recommended) OR accept mongo_user_id
     * 4) Create MySQL withdrawal intent
     * 5) (optional) Reserve payout cents
     * 6) Debit Diamonds in Mongo with idempotency key
     * 7) Finalize wallet + ledger + mark successful
     * 8) On any failure: rollback reservation + mark failed
     */
    public function store(StoreOfflineWithdrawalRequest $request, MongoEconomyService $mongoEconomy)
    {
        $user = $request->user();
        $data = $request->validated();

        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();

        $diamonds    = (int) ($data['diamonds_amount'] ?? 0);
        $payoutCents = (int) ($data['payout_cents'] ?? 0);

        // Require positive amounts (defensive)
        if ($diamonds <= 0 || $payoutCents <= 0) {
            return response()->json([
                'message' => 'Invalid amounts.',
                'errors' => [
                    'diamonds_amount' => ['Must be > 0'],
                    'payout_cents' => ['Must be > 0'],
                ],
            ], 422);
        }

        // Reserve payout to prevent double payouts during processing
        $reserve = true;

        return DB::transaction(function () use (
            $user,
            $data,
            $idempotencyKey,
            $diamonds,
            $payoutCents,
            $mongoEconomy,
            $reserve
        ) {
            // (1) Idempotency inside txn to avoid races
            $existing = OfflineWithdrawal::query()
                ->where('agent_user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Already processed.',
                    'withdrawal' => $existing,
                ]);
            }

            // (2) Lock or create wallet (FIX for "user_id doesn't have default value")
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                // IMPORTANT: must include user_id AND the columns that exist in your wallets table
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'available_cents' => 0,
                    'reserved_cents'  => 0,
                ]);

                $wallet = Wallet::query()
                    ->where('id', $wallet->id)
                    ->lockForUpdate()
                    ->first();
            }

            // (3) Resolve Mongo target user
            // Prefer user_identification (46634) to avoid wrong ObjectId being passed by client.
            $mongoUserId = $data['mongo_user_id'] ?? null;
            $userIdentification = $data['user_identification'] ?? $data['UserIdentification'] ?? null;

            try {
                if ($userIdentification !== null) {
                    // MongoEconomyService should accept user_identification and resolve internally
                    $resolved = $mongoEconomy->resolveMongoUserIdByUserIdentification($userIdentification);

                    if (!$resolved) {
                        return response()->json([
                            'message' => 'Mongo user not found.',
                            'errors' => ['user_identification' => ['User not found in Mongo users collection.']],
                        ], 422);
                    }

                    $mongoUserId = $resolved['mongo_user_id'];
                }

                if (!$mongoUserId) {
                    return response()->json([
                        'message' => 'Missing target user.',
                        'errors' => ['mongo_user_id' => ['mongo_user_id or user_identification is required.']],
                    ], 422);
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Mongo lookup failed.',
                    'error' => $e->getMessage(),
                ], 422);
            }

            // (4) Create MySQL intent
            $withdrawal = OfflineWithdrawal::create([
                'agent_user_id'      => $user->id,
                'mongo_user_id'      => $mongoUserId,
                'user_identification'=> $userIdentification ? (string) $userIdentification : null,
                'diamonds_amount'    => $diamonds,
                'payout_cents'       => $payoutCents,
                'currency'           => $data['currency'] ?? 'PHP',
                'payout_method'      => $data['payout_method'],
                'reference'          => $data['reference'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'status'             => OfflineWithdrawal::STATUS_PROCESSING,
                'idempotency_key'    => $idempotencyKey,
                'mongo_txn_ref'      => null,
                'error_payload'      => null,
            ]);

            // (5) Reserve payout
            if ($reserve) {
                if ((int) $wallet->available_cents < $payoutCents) {
                    $withdrawal->update([
                        'status' => OfflineWithdrawal::STATUS_FAILED,
                        'error_payload' => ['wallet' => 'Insufficient available balance to reserve payout.'],
                    ]);

                    AuditLogger::record(
                        'offline_withdrawal_failed_reserve',
                        'offline_withdrawal',
                        (string) $withdrawal->id,
                        [
                            'agent_user_id'   => $user->id,
                            'available_cents' => (int) $wallet->available_cents,
                            'payout_cents'    => $payoutCents,
                        ]
                    );

                    return response()->json([
                        'message' => 'Insufficient wallet balance.',
                        'withdrawal' => $withdrawal,
                    ], 422);
                }

                $wallet->available_cents = (int) $wallet->available_cents - $payoutCents;
                $wallet->reserved_cents  = (int) $wallet->reserved_cents + $payoutCents;
                $wallet->save();
            }

            // (6) Mongo debit + (7) finalize
            try {
                $mongoResult = $mongoEconomy->debitDiamonds(
                    $mongoUserId,
                    $diamonds,
                    $idempotencyKey,
                    [
                        'source'                => 'offline_agent_withdrawal',
                        'agent_user_id'         => $user->id,
                        'offline_withdrawal_id' => $withdrawal->id,
                        'user_identification'   => $withdrawal->user_identification,
                    ]
                );

                // Finalize wallet
                if ($reserve) {
                    $wallet->reserved_cents = (int) $wallet->reserved_cents - $payoutCents;
                } else {
                    if ((int) $wallet->available_cents < $payoutCents) {
                        throw new \RuntimeException('Insufficient wallet balance for payout.');
                    }
                    $wallet->available_cents = (int) $wallet->available_cents - $payoutCents;
                }
                $wallet->save();

                // Ledger
                LedgerEntry::create([
                    'wallet_id'     => $wallet->id,
                    'event_type'    => LedgerEntry::EVENT_OFFLINE_WITHDRAWAL,
                    'event_id'      => $withdrawal->id,
                    'direction'     => LedgerEntry::DIR_DEBIT,
                    'amount_cents'  => $payoutCents,
                    'currency'      => $withdrawal->currency,
                    'meta'          => [
                        'mongo_user_id'      => $withdrawal->mongo_user_id,
                        'user_identification'=> $withdrawal->user_identification,
                        'diamonds_amount'    => $diamonds,
                        'mongo_txn_ref'      => $mongoResult['transactionRef'] ?? $idempotencyKey,
                    ],
                ]);

                $withdrawal->update([
                    'status'       => OfflineWithdrawal::STATUS_SUCCESSFUL,
                    'mongo_txn_ref' => $mongoResult['transactionRef'] ?? $idempotencyKey,
                ]);

                AuditLogger::record(
                    'offline_withdrawal_success',
                    'offline_withdrawal',
                    (string) $withdrawal->id,
                    [
                        'agent_user_id'       => $user->id,
                        'mongo_user_id'       => $withdrawal->mongo_user_id,
                        'user_identification' => $withdrawal->user_identification,
                        'diamonds_amount'     => $diamonds,
                        'payout_cents'        => $payoutCents,
                        'mongo_txn_ref'       => $withdrawal->mongo_txn_ref,
                    ]
                );

                return response()->json([
                    'message'    => 'Withdrawal successful.',
                    'withdrawal' => $withdrawal->fresh(),
                ]);
            } catch (\Throwable $e) {
                // (8) Rollback reservation
                if ($reserve) {
                    $wallet->available_cents = (int) $wallet->available_cents + $payoutCents;
                    $wallet->reserved_cents  = (int) $wallet->reserved_cents - $payoutCents;
                    $wallet->save();
                }

                $withdrawal->update([
                    'status' => OfflineWithdrawal::STATUS_FAILED,
                    'error_payload' => [
                        'message' => $e->getMessage(),
                        'class'   => get_class($e),
                    ],
                ]);

                AuditLogger::record(
                    'offline_withdrawal_failed',
                    'offline_withdrawal',
                    (string) $withdrawal->id,
                    [
                        'agent_user_id'       => $user->id,
                        'mongo_user_id'       => $withdrawal->mongo_user_id,
                        'user_identification' => $withdrawal->user_identification,
                        'diamonds_amount'     => $diamonds,
                        'payout_cents'        => $payoutCents,
                        'error'               => $e->getMessage(),
                    ]
                );

                return response()->json([
                    'message'    => 'Withdrawal failed.',
                    'withdrawal' => $withdrawal->fresh(),
                ], 422);
            }
        });
    }
}
