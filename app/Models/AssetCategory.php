<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'fixed_asset_account_id',
        'purchase_payable_account_id',
        'accumulated_depreciation_account_id',
        'depreciation_expense_account_id',
        'prepaid_rent_account_id',
        'rent_expense_account_id'
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function maintenanceTypes(): HasMany
    {
        return $this->hasMany(AssetMaintenanceType::class, 'asset_category_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'asset_category_company')
            ->withTimestamps();
    }

    public function fixedAssetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fixed_asset_account_id');
    }

    public function purchasePayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_payable_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_expense_account_id');
    }

    public function prepaidRentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'prepaid_rent_account_id');
    }

    public function rentExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'rent_expense_account_id');
    }
} 