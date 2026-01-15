<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $table = 'ledger_entries';

    // Event types
    public const EVENT_OFFLINE_WITHDRAWAL = 'offline_withdrawal';

    // Directions
    public const DIR_DEBIT  = 'debit';
    public const DIR_CREDIT = 'credit';

    protected $fillable = [
        'wallet_id',
        'event_type',
        'event_id',
        'direction',
        'amount_cents',
        'currency',
        'meta',
    ];

    protected $casts = [
        'wallet_id'    => 'integer',
        'event_id'     => 'integer',
        'amount_cents' => 'integer',
        'meta'         => 'array',
    ];
}
