<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'category_id',
        'name',
        'asset_type',
        'acquisition_type',
        'serial_number',
        'status',
        'current_value',
        'residual_value',
        'purchase_cost',
        'purchase_date',
        'supplier',
        'down_payment',
        'financing_amount',
        'interest_rate',
        'financing_term_months',
        'first_payment_date',
        'first_depreciation_date',
        'rental_start_date',
        'rental_end_date',
        'rental_period',
        'rental_amount',
        'amortization_term_months',
        'first_amortization_date',
        'accumulated_amortization',
        'last_amortization_date',
        'rental_terms',
        'payment_frequency',
        'depreciation_method',
        'useful_life_months',
        'salvage_value',
        'revaluation_method',
        'last_revaluation_date',
        'last_revaluation_amount',
        'revaluation_notes',
        'is_impaired',
        'impairment_amount',
        'impairment_date',
        'impairment_notes',
        'department',
        'location',
        'warranty_expiry',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'rental_start_date' => 'date',
        'rental_end_date' => 'date',
        'first_payment_date' => 'date',
        'first_depreciation_date' => 'date',
        'first_amortization_date' => 'date',
        'last_amortization_date' => 'date',
        'last_revaluation_date' => 'date',
        'impairment_date' => 'date',
        'is_impaired' => 'boolean',
        'purchase_cost' => 'decimal:2',
        'current_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'financing_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'rental_amount' => 'decimal:2',
        'accumulated_amortization' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'last_revaluation_amount' => 'decimal:2',
        'impairment_amount' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenanceRecord::class);
    }

    public function financingPayments(): HasMany
    {
        return $this->hasMany(AssetFinancingPayment::class);
    }

    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(AssetDepreciationEntry::class);
    }

    public function rentalPayments(): HasMany
    {
        return $this->hasMany(AssetRentalPayment::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class);
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function calculateDepreciation()
    {
        if (!in_array($this->acquisition_type, ['outright_purchase', 'financed_purchase'])) {
            return null;
        }

        $age = now()->diffInMonths($this->purchase_date);
        if ($age >= $this->useful_life_months) {
            return $this->salvage_value;
        }

        if ($this->depreciation_method === 'straight-line') {
            $monthlyDepreciation = ($this->purchase_cost - $this->salvage_value) / $this->useful_life_months;
            return $this->purchase_cost - ($monthlyDepreciation * $age);
        }

        if ($this->depreciation_method === 'declining-balance') {
            $rate = 2 / $this->useful_life_months; // Double declining rate
            return $this->purchase_cost * pow((1 - $rate), $age);
        }

        return $this->purchase_cost;
    }

    public function calculateAmortization()
    {
        if ($this->acquisition_type !== 'fixed_rental') {
            return null;
        }

        if (!$this->first_amortization_date || !$this->rental_amount || !$this->amortization_term_months) {
            return null;
        }

        $age = now()->diffInMonths($this->first_amortization_date);
        if ($age >= $this->amortization_term_months) {
            return 0;
        }

        $monthlyAmortization = $this->rental_amount / $this->amortization_term_months;
        return $this->rental_amount - ($monthlyAmortization * $age);
    }
} 