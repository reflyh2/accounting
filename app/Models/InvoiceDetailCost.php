<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDetailCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:4',
        'amount_base' => 'decimal:4',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class);
    }

    public function costEntry(): BelongsTo
    {
        return $this->belongsTo(CostEntry::class);
    }

    public function inventoryCostConsumption(): BelongsTo
    {
        return $this->belongsTo(InventoryCostConsumption::class);
    }

    public function costAllocation(): BelongsTo
    {
        return $this->belongsTo(CostAllocation::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeFromInventory($query)
    {
        return $query->where('cost_source', 'inventory');
    }

    public function scopeFromDirect($query)
    {
        return $query->where('cost_source', 'direct');
    }

    public function scopeFromAllocated($query)
    {
        return $query->where('cost_source', 'allocated');
    }
}
