<?php

namespace App\Models;

use App\Enums\CostEntrySource;
use App\Enums\CostObjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'source_type' => CostEntrySource::class,
        'cost_object_type' => CostObjectType::class,
        'amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'amount_base' => 'decimal:4',
        'amount_allocated' => 'decimal:4',
        'is_fully_allocated' => 'boolean',
        'cost_date' => 'date',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function costPool(): BelongsTo
    {
        return $this->belongsTo(CostPool::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    /**
     * Polymorphic relationship to the source document.
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    /**
     * Polymorphic relationship to the cost object.
     */
    public function costObject(): MorphTo
    {
        return $this->morphTo('cost_object', 'cost_object_type', 'cost_object_id');
    }

    /**
     * Invoice detail costs linked to this entry.
     */
    public function invoiceDetailCosts(): HasMany
    {
        return $this->hasMany(InvoiceDetailCost::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeUnallocated($query)
    {
        return $query->where('is_fully_allocated', false);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get the remaining unallocated amount.
     */
    public function getUnallocatedAmountAttribute(): float
    {
        return (float) $this->amount_base - (float) $this->amount_allocated;
    }

    /**
     * Mark a portion as allocated.
     */
    public function recordAllocation(float $amount): void
    {
        $this->amount_allocated = (float) $this->amount_allocated + $amount;
        $this->is_fully_allocated = abs($this->unallocated_amount) < 0.0001;
        $this->save();
    }
}
