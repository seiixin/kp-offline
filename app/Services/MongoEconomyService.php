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

    /* =====================================================
     | COLLECTIONS
     ===================================================== */
    private function players(): \MongoDB\Collection
    {
        // PLAYERS
        return $this->db()->selectCollection('users');
    }

    private function agencyMembers(): \MongoDB\Collection
    {
        // AGENCY MEMBERS / OWNERS
        return $this->db()->selectCollection('agencymembers');
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
     | PLAYER LOOKUP (USED BY WITHDRAWALS / RECHARGES)
     | ⚠️ DO NOT CHANGE LOGIC
     ===================================================== */
    public function getUserBasicByMongoId(string $mongoUserId): ?array
    {
        try {
            $doc = $this->players()->findOne(
                ['_id' => $this->toObjectId($mongoUserId)],
                ['projection' => ['FullName' => 1, 'Username' => 1]]
            );

            if (!$doc) {
                return null;
            }

            if ($doc instanceof BSONDocument) {
                $doc = $doc->getArrayCopy();
            }

            return [
                'full_name' => $doc['FullName'] ?? null,
                'username'  => $doc['Username'] ?? null,
                'type'      => 'player',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /* =====================================================
     | PLAYER DROPDOWN (WITHDRAWALS / RECHARGES)
     ===================================================== */
public function listUsersForDropdown(array $opts = []): array
{
    $items = [];
    $limit = $opts['limit'] ?? 50;
    $q     = !empty($opts['q']) ? trim($opts['q']) : null;

    /* =====================================================
     | 1) PLAYERS (USERS) — UNCHANGED
     ===================================================== */
    $playerFilter = [];

    if ($q) {
        $playerFilter['$or'] = [
            ['FullName' => new Regex($q, 'i')],
            ['Username' => new Regex($q, 'i')],
        ];
    }

    $players = $this->players()->find(
        $playerFilter,
        [
            'projection' => [
                'FullName' => 1,
                'Username' => 1,
            ],
            'limit' => $limit,
            'sort'  => ['Username' => 1],
        ]
    );

    foreach ($players as $doc) {
        if ($doc instanceof BSONDocument) {
            $doc = $doc->getArrayCopy();
        }

        $items[] = [
            'value' => (string) $doc['_id'],
            'label' => trim(
                ($doc['FullName'] ?? 'Unknown') .
                ' (@' . ($doc['Username'] ?? 'n/a') . ')'
            ),
        ];
    }

    /* =====================================================
     | 2) AGENCY MEMBERS (AGENTS) — WITH FullName
     ===================================================== */
    $agentFilter = [];

    if ($q) {
        $agentFilter['$or'] = [
            ['FullName' => new Regex($q, 'i')],
            ['userIdentification' => new Regex($q, 'i')],
        ];
    }

    $agents = $this->agencymembers()->find(
        $agentFilter,
        [
            'projection' => [
                'FullName'           => 1,
                'userIdentification' => 1,
                'role'               => 1,
            ],
            'limit' => $limit,
            'sort'  => ['FullName' => 1],
        ]
    );

    foreach ($agents as $doc) {
        if ($doc instanceof BSONDocument) {
            $doc = $doc->getArrayCopy();
        }

        $name = $doc['FullName']
            ?? $doc['userIdentification']
            ?? 'Unknown Agent';

        $items[] = [
            'value' => (string) $doc['_id'],
            'label' => $name . ' (Agent)',
        ];
    }

    return $items;
}

    /* =====================================================
     | AGENCY MEMBER DROPDOWN (ADMIN / AGENTS MGMT)
     ===================================================== */
public function listAgencyMembersForDropdown(array $opts = []): array
{
    $filter = [];
    $limit  = $opts['limit'] ?? 50;

    if (!empty($opts['q'])) {
        $q = trim($opts['q']);
        $filter['$or'] = [
            ['FullName' => new Regex($q, 'i')],
            ['userIdentification' => new Regex($q, 'i')],
        ];
    }

    $cursor = $this->agencyMembers()->find(
        $filter,
        [
            'projection' => [
                'FullName'           => 1,
                'userIdentification' => 1,
                'role'               => 1,
            ],
            'limit' => $limit,
            'sort'  => ['FullName' => 1],
        ]
    );

    $items = [];

    foreach ($cursor as $member) {
        if ($member instanceof BSONDocument) {
            $member = $member->getArrayCopy();
        }

        $name = $member['FullName']
            ?? $member['userIdentification']
            ?? 'Unknown';

        $items[] = [
            'value' => (string) $member['_id'],
            'label' => $name . (
                !empty($member['role'])
                    ? ' — ' . $member['role']
                    : ''
            ),
        ];
    }

    return $items;
}

    /* =====================================================
     | PLAYER DIAMOND DEBIT (WITHDRAWAL)
     | ⚠️ PLAYER ONLY — DO NOT TOUCH
     ===================================================== */
    public function reserveDiamonds(string $mongoUserId, int $diamonds): void
    {
        if ($diamonds <= 0) return;

        $res = $this->players()->updateOne(
            [
                '_id' => $this->toObjectId($mongoUserId),
                'Diamonds' => ['$gte' => $diamonds],
            ],
            [
                '$inc' => ['Diamonds' => -$diamonds],
            ]
        );

        if ($res->getModifiedCount() !== 1) {
            throw new RuntimeException('Insufficient player diamonds.');
        }
    }

    public function releaseReservedDiamonds(string $mongoUserId, int $diamonds): void
    {
        if ($diamonds <= 0) return;

        $this->players()->updateOne(
            ['_id' => $this->toObjectId($mongoUserId)],
            ['$inc' => ['Diamonds' => $diamonds]]
        );
    }

    /* =====================================================
     | CREDIT PLAYER COINS (RECHARGE)
     | ⚠️ PLAYER ONLY — DO NOT TOUCH
     ===================================================== */
    public function creditCoins(array $payload): array
    {
        $mongoUserId    = (string) ($payload['mongo_user_id'] ?? '');
        $coins          = (int) ($payload['coins_amount'] ?? 0);
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
        $meta           = (array) ($payload['meta'] ?? []);
        $source         = $payload['source'] ?? 'offline_agent_recharge';

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

        $existing = $this->transactions()->findOne(
            ['transactionRef' => $idempotencyKey],
            ['projection' => ['status' => 1]]
        );

        if ($existing) {
            return [
                'transactionRef' => $idempotencyKey,
                'status' => $existing['status'] ?? 'successful',
                'idempotent' => true,
            ];
        }

        $oid = $this->toObjectId($mongoUserId);

        $txn = $this->transactions()->insertOne([
            'transactionRef' => $idempotencyKey,
            'userId' => $oid,
            'status' => 'pending',
            'coinsCredited' => $coins,
            'source' => $source,
            'meta' => $meta,
            'createdAt' => now()->toDateTimeString(),
            'updatedAt' => now()->toDateTimeString(),
        ]);

        $res = $this->players()->updateOne(
            ['_id' => $oid],
            ['$inc' => ['Coins' => $coins]]
        );

        if ($res->getModifiedCount() !== 1) {
            $this->transactions()->updateOne(
                ['_id' => $txn->getInsertedId()],
                ['$set' => ['status' => 'failed']]
            );

            throw new RuntimeException('User not found.');
        }

        $this->transactions()->updateOne(
            ['_id' => $txn->getInsertedId()],
            ['$set' => ['status' => 'successful']]
        );

        return [
            'transactionRef' => $idempotencyKey,
            'status' => 'successful',
            'idempotent' => false,
        ];
    }
}
