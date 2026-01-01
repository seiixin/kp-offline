<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\Client as MongoClient;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MongoEconomyService
{
    private bool $useLaravelConnection = false;

    private string $uri;
    private string $dbName;
    private string $usersCollection;
    private string $transactionsCollection;

    public function __construct()
    {
        // Prefer the already-configured Laravel mongodb connection (matches what you tested in tinker)
        try {
            DB::connection('mongodb')->getClient();
            $this->useLaravelConnection = true;
        } catch (\Throwable $e) {
            $this->useLaravelConnection = false;
        }

        $this->uri = (string) (config('database.connections.mongodb.dsn')
            ?: config('database.connections.mongodb.uri')
            ?: config('services.mongo.uri')
            ?: env('MONGO_URI', ''));

        // Prefer Laravel connection config; fallback to env
        $this->dbName = (string) (config('database.connections.mongodb.database')
            ?: config('services.mongo.db')
            ?: config('services.mongo.database')
            ?: env('MONGO_DB', '')
            ?: env('MONGO_DATABASE', ''));

        $this->usersCollection = (string) (config('services.mongo.users_collection') ?: env('MONGO_USERS_COLLECTION', 'users'));
        $this->transactionsCollection = (string) (config('services.mongo.transactions_collection') ?: env('MONGO_TRANSACTIONS_COLLECTION', 'transactions'));

        if ($this->dbName === '') {
            throw new \RuntimeException('Mongo configuration missing. Set database for mongodb connection or set MONGO_DB/MONGO_DATABASE.');
        }
        if (!$this->useLaravelConnection && $this->uri === '') {
            throw new \RuntimeException('Mongo configuration missing. Set mongodb connection or set MONGO_URI.');
        }
    }

    /**
     * Credits coins to a Mongo user and writes a transaction doc (idempotent by transactionRef).
     *
     * Expected payload:
     * - mongo_user_id (24-char hex)
     * - coins_amount (int)
     * - idempotency_key (string)
     * - source (string)
     * - meta (array)
     */
    public function creditCoins(array $payload): array
    {
        $mongoUserId = (string) $payload['mongo_user_id'];
        $coinsAmount = (int) $payload['coins_amount'];
        $transactionRef = (string) $payload['idempotency_key'];
        $source = (string) ($payload['source'] ?? 'offline_agent');
        $meta = (array) ($payload['meta'] ?? []);

        $client = $this->getClient();
        $db = $client->selectDatabase($this->dbName);

        $users = $db->selectCollection($this->usersCollection);
        $txns = $db->selectCollection($this->transactionsCollection);

        $now = new UTCDateTime((int) (microtime(true) * 1000));
        $userObjectId = new ObjectId($mongoUserId);

        // Ensure user exists (also fetch coin fields to determine correct casing)
        $user = $users->findOne(
            ['_id' => $userObjectId],
            ['projection' => ['_id' => 1, 'coins' => 1, 'Coins' => 1]]
        );

        if (!$user) {
            throw new \RuntimeException('Mongo user not found in db=' . $this->dbName . ' collection=' . $this->usersCollection . ' id=' . $mongoUserId);
        }

        // Determine which coin field to increment (some docs may use Coins vs coins)
        $coinField = 'coins';
        if (isset($user['coins'])) {
            $coinField = 'coins';
        } elseif (isset($user['Coins'])) {
            $coinField = 'Coins';
        }

        // Idempotency check
        $existing = $txns->findOne(['transactionRef' => $transactionRef]);
        if ($existing && (($existing['status'] ?? null) === 'successful')) {
            return [
                'already_processed' => true,
                'transactionRef' => $transactionRef,
                'transactionId' => (string) ($existing['_id'] ?? ''),
                'status' => 'successful',
                'coin_field' => $coinField,
                'db' => $this->dbName,
                'users_collection' => $this->usersCollection,
            ];
        }

        // If doc exists and pending, try to "claim" it by switching to applying
        if ($existing) {
            $status = (string) ($existing['status'] ?? 'pending');

            if ($status === 'pending') {
                $claimed = $txns->findOneAndUpdate(
                    ['transactionRef' => $transactionRef, 'status' => 'pending'],
                    ['$set' => ['status' => 'applying', 'updatedAt' => $now]],
                    ['returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
                );
                if (!$claimed) {
                    // someone else claimed; re-fetch and return processing/success
                    $fresh = $txns->findOne(['transactionRef' => $transactionRef]);
                    $freshStatus = (string) ($fresh['status'] ?? 'pending');
                    return [
                        'already_processed' => $freshStatus === 'successful',
                        'transactionRef' => $transactionRef,
                        'transactionId' => (string) ($fresh['_id'] ?? ''),
                        'status' => $freshStatus,
                        'coin_field' => $coinField,
                        'db' => $this->dbName,
                        'users_collection' => $this->usersCollection,
                    ];
                }
                $existing = $claimed;
            } elseif ($status === 'applying') {
                return [
                    'already_processed' => false,
                    'transactionRef' => $transactionRef,
                    'transactionId' => (string) ($existing['_id'] ?? ''),
                    'status' => 'applying',
                    'coin_field' => $coinField,
                    'db' => $this->dbName,
                    'users_collection' => $this->usersCollection,
                ];
            } elseif ($status === 'failed') {
                // allow retry: set to pending again and proceed
                $txns->updateOne(['_id' => $existing['_id']], ['$set' => ['status' => 'pending', 'updatedAt' => $now]]);
                $existing = $txns->findOne(['transactionRef' => $transactionRef]);
            }
        }

        // Create doc if missing
        if (!$existing) {
            $insert = $txns->insertOne([
                'transactionRef' => $transactionRef,
                'userId' => $userObjectId,
                'status' => 'applying',
                'coinsFinal' => $coinsAmount,
                'source' => $source,
                'meta' => $meta,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);
            $existing = $txns->findOne(['_id' => $insert->getInsertedId()]);
        }

        // Apply economy: increment user coins
        try {
            $users->updateOne(
                ['_id' => $userObjectId],
                ['$inc' => [$coinField => $coinsAmount], '$set' => ['updatedAt' => $now]]
            );

            $txns->updateOne(
                ['transactionRef' => $transactionRef],
                ['$set' => ['status' => 'successful', 'updatedAt' => $now]]
            );

            return [
                'already_processed' => false,
                'transactionRef' => $transactionRef,
                'transactionId' => (string) ($existing['_id'] ?? ''),
                'status' => 'successful',
                'coin_field' => $coinField,
                'db' => $this->dbName,
                'users_collection' => $this->usersCollection,
            ];
        } catch (\Throwable $e) {
            // mark failed with error payload
            $txns->updateOne(
                ['transactionRef' => $transactionRef],
                ['$set' => ['status' => 'failed', 'error' => $e->getMessage(), 'updatedAt' => $now]]
            );
            throw $e;
        }
    }

    private function getClient(): MongoClient
    {
        if ($this->useLaravelConnection) {
            return DB::connection('mongodb')->getClient();
        }
        return new MongoClient($this->uri);
    }
}
