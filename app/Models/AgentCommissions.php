<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    use HasFactory;

    protected $table = 'agent_commissions';

    /**
     * Mass-assignable fields
     */
    protected $fillable = [
        'agent_id',
        'commission_amount_cents',
        'commission_percentage',
        'reference',
        'status',
        'created_by',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'agent_id'                 => 'integer',
        'commission_amount_cents'  => 'integer',
        'commission_percentage'    => 'float',
        'created_by'               => 'integer',
    ];

    /**
     * Commission status constants
     */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    /* =====================================================
     | RELATIONSHIPS
     ===================================================== */

    /**
     * Agent who earned the commission
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * User who created / posted the commission
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =====================================================
     | HELPERS
     ===================================================== */

    /**
     * Check if commission is payable
     */
    public function isPayable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Mark commission as paid
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
        ]);
    }

    /**
     * Mark commission as cancelled
     */
    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }
}
