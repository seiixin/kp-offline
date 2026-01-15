<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineWithdrawal extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'agent_user_id',
        'mongo_user_id',
        'diamonds_amount',
        'payout_cents',
        'currency',
        'payout_method',
        'reference',
        'notes',
        'status',
        'idempotency_key',
        'mongo_txn_ref',
        'error_payload',
    ];

    protected $casts = [
        'diamonds_amount' => 'integer',
        'payout_cents' => 'integer',
        'error_payload' => 'array',
    ];
}
