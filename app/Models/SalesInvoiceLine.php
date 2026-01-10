<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_base' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'cost_total' => 'decimal:4',
        'gross_margin' => 'decimal:4',
        'line_total' => 'decimal:2',
        'line_total_base' => 'decimal:4',
        'delivery_value_base' => 'decimal:4',
        'revenue_variance' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function salesDeliveryLine(): BelongsTo
    {
        return $this->belongsTo(SalesDeliveryLine::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Costing Relationships
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Costs linked to this invoice line.
     */
    public function costs(): HasMany
    {
        return $this->hasMany(InvoiceDetailCost::class);
    }

    /**
     * Allocations from cost pools to this line.
     */
    public function costAllocations(): HasMany
    {
        return $this->hasMany(CostAllocation::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Costing Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get total cost from all sources (inventory, direct, allocated).
     */
    public function getTotalCostAttribute(): float
    {
        return (float) $this->costs()->sum('amount_base');
    }

    /**
     * Get computed gross margin.
     */
    public function getComputedGrossMarginAttribute(): float
    {
        return (float) $this->line_total_base - $this->total_cost;
    }

    /**
     * Get gross margin percentage.
     */
    public function getGrossMarginPercentAttribute(): ?float
    {
        if ((float) $this->line_total_base === 0.0) {
            return null;
        }

        return ($this->computed_gross_margin / (float) $this->line_total_base) * 100;
    }
}
