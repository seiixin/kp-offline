<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    /**
     * FIX:
     * Your table requires user_id (NOT NULL, no default), but user_id was not fillable,
     * so Wallet::create([...]) dropped it and only inserted timestamps.
     *
     * Add 'user_id' to fillable so mass assignment includes it.
     */
    protected $fillable = [
        'user_id',          
        'owner_type',
        'owner_id',
        'asset',
        'available_cents',
        'reserved_cents',
    ];

    protected $casts = [
        'user_id'          => 'integer',
        'owner_id'         => 'integer',
        'available_cents'  => 'integer',
        'reserved_cents'   => 'integer',
    ];

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'wallet_id');
    }

    public function getBalanceCentsAttribute(): int
    {
        return (int) $this->available_cents + (int) $this->reserved_cents;
    }

    /**
     * Optional helper: get/create the wallet for a user.
     * Keeps your controllers clean and guarantees user_id is always set.
     */
    public static function firstOrCreateForUser(int $userId, string $asset = 'PHP'): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'asset' => $asset],
            [
                'owner_type'      => 'user',
                'owner_id'        => $userId,
                'available_cents' => 0,
                'reserved_cents'  => 0,
            ]
        );
    }
}
