<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopupTransaction extends Model
{
    use HasFactory;

    // Table name (optional, as Laravel would infer this)
    protected $table = 'topup_transactions';

    // The attributes that are mass assignable
    protected $fillable = [
        'agent_id',
        'reference',
        'amount',
        'status',
        'payment_method',
    ];

    // You can define relationships if needed, for example, the relation with Agent
    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
