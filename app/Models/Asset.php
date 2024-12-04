<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'branch_id',
        'category_id',
        'serial_number',
        'status',
        'purchase_cost',
        'purchase_date',
        'supplier',
        'warranty_expiry',
        'depreciation_method',
        'useful_life_months',
        'salvage_value',
        'notes'
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenanceRecord::class);
    }

    public function calculateDepreciation(): float
    {
        $ageInMonths = now()->diffInMonths($this->purchase_date);
        
        if ($ageInMonths >= $this->useful_life_months) {
            return $this->salvage_value;
        }

        switch ($this->depreciation_method) {
            case 'straight-line':
                $monthlyDepreciation = ($this->purchase_cost - $this->salvage_value) / $this->useful_life_months;
                return $this->purchase_cost - ($monthlyDepreciation * $ageInMonths);
            
            case 'declining-balance':
                $monthlyRate = (2 / $this->useful_life_months); // Double declining balance
                return $this->purchase_cost * pow((1 - $monthlyRate), $ageInMonths);
            
            default:
                return $this->purchase_cost;
        }
    }
} 