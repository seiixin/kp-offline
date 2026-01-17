<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Model\BSONDocument;
use RuntimeException;

class MongoEconomyService
{
    /* =====================================================
     | CORE CONNECTION
     ===================================================== */
    private function db(): \MongoDB\Database
    {
        return DB::connection('mongodb')->getDatabase();
    }

    private function users(): \MongoDB\Collection
    {
        return $this->db()->selectCollection('users');
    }

    private function transactions(): \MongoDB\Collection
    {
        return $this->db()->selectCollection('transactions');
    }

    /* =====================================================
     | HELPERS
     ===================================================== */
    private function toObjectId(string $id): ObjectId
    {
        if (!preg_match('/^[a-f\d]{24}$/i', $id)) {
            throw new \InvalidArgumentException('Invalid mongo_user_id.');
        }
        return new ObjectId($id);
    }

    /* =====================================================
     | USER LOOKUP (FOR UI / LISTING)
     ===================================================== */
    public function getUserBasicByMongoId(string $mongoUserId): ?array
    {
        try {
            $doc = $this->users()->findOne(
                ['_id' => $this->toObjectId($mongoUserId)],
                ['projection' => ['FullName' => 1, 'Username' => 1]]
            );

            if (!$doc) return null;

            if ($doc instanceof BSONDocument) {
                $doc = $doc->getArrayCopy();
            }

            return [
                'full_name' => $doc['FullName'] ?? null,
                'username'  => $doc['Username'] ?? null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function listUsersForDropdown(array $opts = []): array
    {
        $filter = [];

        if (!empty($opts['q'])) {
            $q = trim($opts['q']);
            $filter['$or'] = [
                ['FullName' => new Regex($q, 'i')],
                ['Username' => new Regex($q, 'i')],
            ];
        }

        $cursor = $this->users()->find(
            $filter,
            [
                'projection' => ['FullName' => 1, 'Username' => 1],
                'limit' => $opts['limit'] ?? 20,
                'sort'  => ['Username' => 1],
            ]
        );

        $items = [];
        foreach ($cursor as $doc) {
            if ($doc instanceof BSONDocument) {
                $doc = $doc->getArrayCopy();
            }

            $items[] = [
                'value' => (string) $doc['_id'],
                'label' => trim(
                    ($doc['FullName'] ?? '') .
                    ' (@' . ($doc['Username'] ?? '') . ')'
                ),
            ];
        }

        return $items;
    }

    /* =====================================================
     | PLAYER DIAMOND DEBIT (WITHDRAWAL)
     | ⚠️ THIS IS PLAYER SIDE ONLY
     ===================================================== */
    public function reserveDiamonds(string $mongoUserId, int $diamonds): void
    {
        if ($diamonds <= 0) return;

        $res = $this->users()->updateOne(
            [
                '_id' => $this->toObjectId($mongoUserId),
                'Diamonds' => ['$gte' => $diamonds],
            ],
            [
                '$inc' => [
                    'Diamonds' => -$diamonds,
                ],
            ]
        );

        if ($res->getModifiedCount() !== 1) {
            throw new RuntimeException('Insufficient player diamonds.');
        }
    }

    /* =====================================================
     | RELEASE PLAYER DIAMONDS (CANCEL / FAIL)
     ===================================================== */
    public function releaseReservedDiamonds(string $mongoUserId, int $diamonds): void
    {
        if ($diamonds <= 0) return;

        $this->users()->updateOne(
            ['_id' => $this->toObjectId($mongoUserId)],
            [
                '$inc' => [
                    'Diamonds' => $diamonds,
                ],
            ]
        );
    }

    /* =====================================================
     | CREDIT PLAYER COINS (RECHARGE)
     ===================================================== */
    public function creditCoins(array $payload): array
    {
        $mongoUserId     = (string) ($payload['mongo_user_id'] ?? '');
        $coins           = (int) ($payload['coins_amount'] ?? 0);
        $idempotencyKey  = (string) ($payload['idempotency_key'] ?? '');
        $meta            = (array) ($payload['meta'] ?? []);
        $source          = $payload['source'] ?? 'offline_agent_recharge';

        if ($mongoUserId === '' || $idempotencyKey === '') {
            throw new \InvalidArgumentException('mongo_user_id and idempotency_key are required.');
        }

        if ($coins <= 0) {
            return [
                'transactionRef' => $idempotencyKey,
                'status' => 'successful',
                'idempotent' => true,
            ];
        }

        // idempotency check
        $existing = $this->transactions()->findOne(
            ['transactionRef' => $idempotencyKey],
            ['projection' => ['_id' => 1, 'status' => 1]]
        );

        if ($existing) {
            $ex = (array) $existing;
            return [
                'transactionRef' => $idempotencyKey,
                'status' => $ex['status'] ?? 'successful',
                'idempotent' => true,
            ];
        }

        $oid = $this->toObjectId($mongoUserId);

        $insert = $this->transactions()->insertOne([
            'transactionRef' => $idempotencyKey,
            'userId' => $oid,
            'status' => 'pending',
            'coinsCredited' => $coins,
            'source' => $source,
            'meta' => $meta,
            'createdAt' => now()->toDateTimeString(),
            'updatedAt' => now()->toDateTimeString(),
        ]);

        $txnId = $insert->getInsertedId();

        $res = $this->users()->updateOne(
            ['_id' => $oid],
            ['$inc' => ['Coins' => $coins]]
        );

        if ($res->getModifiedCount() !== 1) {
            $this->transactions()->updateOne(
                ['_id' => $txnId],
                [
                    '$set' => [
                        'status' => 'failed',
                        'updatedAt' => now()->toDateTimeString(),
                    ],
                ]
            );
            throw new RuntimeException('User not found.');
        }

        $this->transactions()->updateOne(
            ['_id' => $txnId],
            [
                '$set' => [
                    'status' => 'successful',
                    'updatedAt' => now()->toDateTimeString(),
                ],
            ]
        );

        return [
            'transactionRef' => $idempotencyKey,
            'status' => 'successful',
            'idempotent' => false,
        ];
    }
}
