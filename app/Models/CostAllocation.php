<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostAllocation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:4',
        'amount_base' => 'decimal:4',
        'allocation_numerator' => 'decimal:6',
        'allocation_denominator' => 'decimal:6',
        'allocation_ratio' => 'decimal:8',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function costPool(): BelongsTo
    {
        return $this->belongsTo(CostPool::class);
    }

    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get a human-readable description of the allocation basis.
     */
    public function getAllocationDescriptionAttribute(): string
    {
        return sprintf(
            '%s of %s (%s = %.2f%%)',
            number_format($this->allocation_numerator, 2),
            number_format($this->allocation_denominator, 2),
            $this->allocation_rule,
            $this->allocation_ratio * 100
        );
    }
}
