<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTopUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'amount_usd_cents',
        'reference',
        'status',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'agent_id' => 'integer',
        'amount_usd_cents' => 'integer',
        'created_by' => 'integer',
        'posted_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
