<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineWithdrawal extends Model
{
    public const STATUS_PROCESSING = 'processing';   // agent submitted
    public const STATUS_APPROVED   = 'approved';     // admin approved
    public const STATUS_PAID       = 'paid';         // cash sent
    public const STATUS_COMPLETED  = 'completed';    // final
    public const STATUS_CANCELLED  = 'cancelled';
    public const STATUS_FAILED     = 'failed';
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
