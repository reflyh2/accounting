<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingAllocationRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'denominator' => 'decimal:4',
        'pool_amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function costPool(): BelongsTo
    {
        return $this->belongsTo(CostPool::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CostAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by', 'global_id');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by', 'global_id');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForPool($query, int $costPoolId)
    {
        return $query->where('cost_pool_id', $costPoolId);
    }
}
