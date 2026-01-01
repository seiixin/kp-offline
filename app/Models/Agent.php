<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_code',
        'name',
        'phone',
        'country',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'owner_id')
            ->where('owner_type', 'agent');
    }

    public function topUps(): HasMany
    {
        return $this->hasMany(AgentTopUp::class);
    }

    public function offlineRecharges(): HasMany
    {
        return $this->hasMany(OfflineRecharge::class);
    }

    public function offlineWithdrawals(): HasMany
    {
        return $this->hasMany(OfflineWithdrawal::class);
    }
}
