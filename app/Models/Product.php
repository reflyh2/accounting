<?php

namespace App\Models;

use App\Models\CostPool;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attrs_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function attributeSet()
    {
        return $this->belongsTo(AttributeSet::class);
    }

    public function defaultUom()
    {
        return $this->belongsTo(Uom::class, 'default_uom_id');
    }

    public function taxCategory()
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function revenueAccount()
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function cogsAccount()
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function inventoryAccount()
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function prepaidAccount()
    {
        return $this->belongsTo(Account::class, 'prepaid_account_id');
    }

    public function defaultCostPool()
    {
        return $this->belongsTo(CostPool::class, 'default_cost_pool_id');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_product');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function capabilities()
    {
        return $this->hasMany(ProductCapability::class);
    }

    public function supplierLinks()
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function resourcePools()
    {
        return $this->hasMany(ResourcePool::class);
    }

    public function rentalPolicy()
    {
        return $this->hasOne(RentalPolicy::class);
    }

    public function priceListItems()
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }
}


