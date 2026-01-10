<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostPool extends Model
{
    protected $guarded = [];

    protected $casts = [
        'total_accumulated' => 'decimal:4',
        'total_allocated' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    /**
     * Cost entries in this pool.
     */
    public function costEntries(): HasMany
    {
        return $this->hasMany(CostEntry::class);
    }

    /**
     * Allocations from this pool.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CostAllocation::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForAsset($query, int $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get the remaining unallocated amount.
     */
    public function getUnallocatedAmountAttribute(): float
    {
        return (float) $this->total_accumulated - (float) $this->total_allocated;
    }

    /**
     * Record an accumulated cost entry.
     */
    public function recordAccumulation(float $amount): void
    {
        $this->total_accumulated = (float) $this->total_accumulated + $amount;
        $this->save();
    }

    /**
     * Record an allocation from the pool.
     */
    public function recordAllocation(float $amount): void
    {
        $this->total_allocated = (float) $this->total_allocated + $amount;
        $this->save();
    }
}
