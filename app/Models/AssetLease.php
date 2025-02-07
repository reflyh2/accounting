<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLease extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_id',
        'lease_type',
        'start_date',
        'end_date',
        'lease_amount',
        'payment_frequency',
        'payment_amount',
        'prepaid_amount',
        'total_obligation',
        'interest_rate',
        'has_escalation_clause',
        'escalation_terms',
        'lease_terms'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'lease_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'prepaid_amount' => 'decimal:2',
        'total_obligation' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'has_escalation_clause' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AssetLeasePayment::class);
    }

    public function getRemainingObligationAttribute(): float
    {
        return $this->total_obligation - $this->payments()->where('status', 'paid')->sum('amount');
    }

    public function getMonthlyAmortizationAttribute(): float
    {
        if ($this->lease_type === 'operating') {
            return $this->payment_amount;
        }

        // For finance leases, calculate amortization using interest rate
        $periods = $this->start_date->diffInMonths($this->end_date);
        $rate = $this->interest_rate / 12 / 100; // Monthly interest rate
        
        return $this->calculatePMT($rate, $periods, -$this->total_obligation);
    }

    private function calculatePMT($rate, $periods, $present_value): float
    {
        if ($rate == 0) {
            return -$present_value / $periods;
        }

        return $rate * $present_value / (1 - pow(1 + $rate, -$periods));
    }
} 