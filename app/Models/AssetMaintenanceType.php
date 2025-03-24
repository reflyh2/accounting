<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetMaintenanceType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'asset_category_id',
        'maintenance_cost_account_id',
        'description',
        'maintenance_interval',
        'maintenance_interval_days',
    ];

    /**
     * Get the asset category that this maintenance type belongs to.
     */
    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /**
     * Get the account used for maintenance costs.
     */
    public function maintenanceCostAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'maintenance_cost_account_id');
    }

    /**
     * Get the companies associated with this maintenance type.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'asset_maintenance_type_company')
            ->withTimestamps();
    }

    /**
     * Get the maintenance records associated with this type.
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenanceRecord::class, 'maintenance_type_id');
    }
} 