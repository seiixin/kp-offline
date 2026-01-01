<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'direction',
        'amount_cents',
        'event_type',
        'event_id',
        'meta_json',
    ];

    protected $casts = [
        'wallet_id' => 'integer',
        'amount_cents' => 'integer',
        'event_id' => 'integer',
        'meta_json' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
