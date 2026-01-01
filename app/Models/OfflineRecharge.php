<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineRecharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'mongo_user_id',
        'amount_usd_cents',
        'coins_amount',
        'method',
        'reference',
        'status',
        'mobile_txn_ref',
        'idempotency_key',
        'proof_url',
        'meta_json',
    ];

    protected $casts = [
        'agent_id' => 'integer',
        'amount_usd_cents' => 'integer',
        'coins_amount' => 'integer',
        'meta_json' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
