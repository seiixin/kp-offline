<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;

class MongoEconomyService
{
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

    private function isValidObjectId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{24}$/i', $id);
    }

    private function toObjectId(string $id): ObjectId
    {
        if (!$this->isValidObjectId($id)) {
            throw new \InvalidArgumentException('Invalid mongo_user_id (ObjectId expected).');
        }
        return new ObjectId($id);
    }

    public function resolveMongoUserIdByUserIdentification(string|int $userIdentification): ?array
    {
        $uidStr = (string) $userIdentification;

        $doc = $this->users()->findOne(
            [
                '$or' => [
                    ['UserIdentification' => $uidStr],
                    ['UserIdentification' => (int) $uidStr],
                    ['userIdentification' => $uidStr],
                    ['userIdentification' => (int) $uidStr],
                ],
            ],
            [
                'projection' => [
                    '_id' => 1,
                    'UserIdentification' => 1,
                    'userIdentification' => 1,
                    'Diamonds' => 1,
                    'Coins' => 1,
                    'Email' => 1,
                    'Username' => 1,
                ],
            ]
        );

        if (!$doc) return null;

        $arr = (array) $doc;
        $oid = $arr['_id'] ?? null;

        if (!$oid instanceof ObjectId) return null;

        return [
            'mongo_user_id' => (string) $oid,
            'user' => [
                '_id' => (string) $oid,
                'UserIdentification' => $arr['UserIdentification'] ?? ($arr['userIdentification'] ?? null),
                'Diamonds' => isset($arr['Diamonds']) ? (int) $arr['Diamonds'] : null,
                'Coins' => isset($arr['Coins']) ? (int) $arr['Coins'] : null,
                'Email' => $arr['Email'] ?? null,
                'Username' => $arr['Username'] ?? null,
            ],
        ];
    }

    public function debitDiamonds(string $mongoUserId, int $diamonds, string $idempotencyKey, array $meta = []): array
    {
        if ($diamonds <= 0) {
            return [
                'transactionRef' => $idempotencyKey,
                'status' => 'successful',
                'idempotent' => true,
                'note' => 'No-op debit (diamonds <= 0)',
            ];
        }

        // (1) idempotency check
        $existing = $this->transactions()->findOne(
            ['transactionRef' => $idempotencyKey],
            ['projection' => ['_id' => 1, 'transactionRef' => 1, 'status' => 1]]
        );

        if ($existing) {
            $ex = (array) $existing;
            return [
                'transactionRef' => $ex['transactionRef'] ?? $idempotencyKey,
                'status' => $ex['status'] ?? 'successful',
                'id' => isset($ex['_id']) ? (string) $ex['_id'] : null,
                'idempotent' => true,
            ];
        }

        $oid = $this->toObjectId($mongoUserId);

        // (2) create pending transaction
        $insert = $this->transactions()->insertOne([
            'transactionRef' => $idempotencyKey,
            'userId' => $oid,
            'status' => 'pending',
            'diamondsDebited' => (int) $diamonds,
            'source' => $meta['source'] ?? 'offline_agent_withdrawal',
            'meta' => $meta,
            'createdAt' => now()->toDateTimeString(),
            'updatedAt' => now()->toDateTimeString(),
        ]);

        $txnId = $insert->getInsertedId();

        // (3) atomic debit if Diamonds >= diamonds
        $res = $this->users()->updateOne(
            ['_id' => $oid, 'Diamonds' => ['$gte' => (int) $diamonds]],
            ['$inc' => ['Diamonds' => -1 * (int) $diamonds]]
        );

        if ((int) $res->getModifiedCount() <= 0) {
            $this->transactions()->updateOne(
                ['_id' => $txnId],
                [
                    '$set' => [
                        'status' => 'failed',
                        'error' => ['message' => 'Insufficient diamonds or user not found'],
                        'updatedAt' => now()->toDateTimeString(),
                    ],
                ]
            );

            throw new \RuntimeException('Insufficient diamonds or user not found.');
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
            'id' => (string) $txnId,
            'idempotent' => false,
        ];
    }

    public function creditCoins(array $payload): array
    {
        $mongoUserId = (string) ($payload['mongo_user_id'] ?? '');
        $coins = (int) ($payload['coins_amount'] ?? 0);
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
        $meta = (array) ($payload['meta'] ?? []);
        $meta['source'] = $payload['source'] ?? ($meta['source'] ?? 'offline_agent_recharge');

        if ($idempotencyKey === '') throw new \InvalidArgumentException('idempotency_key is required.');
        if ($mongoUserId === '') throw new \InvalidArgumentException('mongo_user_id is required.');

        if ($coins <= 0) {
            return [
                'transactionRef' => $idempotencyKey,
                'status' => 'successful',
                'idempotent' => true,
                'note' => 'No-op credit (coins <= 0)',
            ];
        }

        $existing = $this->transactions()->findOne(
            ['transactionRef' => $idempotencyKey],
            ['projection' => ['_id' => 1, 'transactionRef' => 1, 'status' => 1]]
        );

        if ($existing) {
            $ex = (array) $existing;
            return [
                'transactionRef' => $ex['transactionRef'] ?? $idempotencyKey,
                'status' => $ex['status'] ?? 'successful',
                'id' => isset($ex['_id']) ? (string) $ex['_id'] : null,
                'idempotent' => true,
            ];
        }

        $oid = $this->toObjectId($mongoUserId);

        $insert = $this->transactions()->insertOne([
            'transactionRef' => $idempotencyKey,
            'userId' => $oid,
            'status' => 'pending',
            'coinsCredited' => (int) $coins,
            'source' => $meta['source'] ?? 'offline_agent_recharge',
            'meta' => $meta,
            'createdAt' => now()->toDateTimeString(),
            'updatedAt' => now()->toDateTimeString(),
        ]);

        $txnId = $insert->getInsertedId();

        $res = $this->users()->updateOne(
            ['_id' => $oid],
            ['$inc' => ['Coins' => (int) $coins]]
        );

        if ((int) $res->getModifiedCount() <= 0) {
            $this->transactions()->updateOne(
                ['_id' => $txnId],
                [
                    '$set' => [
                        'status' => 'failed',
                        'error' => ['message' => 'User not found'],
                        'updatedAt' => now()->toDateTimeString(),
                    ],
                ]
            );

            throw new \RuntimeException('User not found.');
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
            'id' => (string) $txnId,
            'idempotent' => false,
        ];
    }
}
