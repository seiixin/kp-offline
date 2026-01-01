<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'asset',
        'available_cents',
        'reserved_cents',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'available_cents' => 'integer',
        'reserved_cents' => 'integer',
    ];

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function getBalanceCentsAttribute(): int
    {
        return (int) $this->available_cents + (int) $this->reserved_cents;
    }
}
