<?php

namespace App\Models\Mongo;

/**
 * Mongo collection: transactions
 * Expected fields (example):
 * - transactionRef (string)  // idempotency key
 * - userId (ObjectId)
 * - status (pending|applying|successful|failed)
 * - coinsFinal (int)
 * - source (string)
 */
class MongoTransaction extends MongoBaseModel
{
    protected $collection = 'transactions';
    protected $guarded = [];
}
