<?php

namespace App\Models\Mongo;

use MongoDB\BSON\ObjectId;

/**
 * Mongo collection: transactions
 *
 * Expected fields:
 * - _id (ObjectId)
 * - transactionRef (string)  // idempotency key (should be unique)
 * - userId (ObjectId)
 * - status (pending|successful|failed)
 * - diamondsDebited (int)    // used by withdrawals
 * - coinsDelta (int)         // optional for other economy ops
 * - source (string)
 * - meta (object/array)
 * - error (object/array)
 */
class MongoTransaction extends MongoBaseModel
{
    protected $collection = 'transactions';

    // allow mass assignment
    protected $guarded = [];

    protected $casts = [
        'diamondsDebited' => 'integer',
        'coinsDelta'      => 'integer',
        'meta'            => 'array',
        'error'           => 'array',
    ];

    /**
     * Ensure userId is stored as ObjectId when provided as a 24-hex string.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (isset($model->userId) && is_string($model->userId) && preg_match('/^[a-f0-9]{24}$/i', $model->userId)) {
                $model->userId = new ObjectId($model->userId);
            }
        });
    }

    /**
     * Idempotency lookup by transactionRef.
     */
    public static function findByRef(string $ref): ?self
    {
        return static::query()->where('transactionRef', $ref)->first();
    }

    /**
     * Create a pending transaction record.
     */
    public static function createPending(string $ref, string $mongoUserId, int $diamondsDebited, string $source, array $meta = []): self
    {
        if (!preg_match('/^[a-f0-9]{24}$/i', $mongoUserId)) {
            throw new \InvalidArgumentException('Invalid mongo_user_id.');
        }

        $txn = new self();
        $txn->transactionRef   = $ref;
        $txn->userId           = new ObjectId($mongoUserId);
        $txn->status           = 'pending';
        $txn->diamondsDebited  = (int) $diamondsDebited;
        $txn->source           = $source;
        $txn->meta             = $meta;
        $txn->error            = null;
        $txn->save();

        return $txn;
    }

    public function markSuccessful(): void
    {
        $this->status = 'successful';
        $this->save();
    }

    public function markFailed(string $message, ?\Throwable $e = null): void
    {
        $this->status = 'failed';
        $this->error = array_filter([
            'message' => $message,
            'class'   => $e ? get_class($e) : null,
        ]);
        $this->save();
    }
}
