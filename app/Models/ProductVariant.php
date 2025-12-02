<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attrs_json' => 'array',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
        'weight_grams' => 'decimal:3',
        'length_cm' => 'decimal:3',
        'width_cm' => 'decimal:3',
        'height_cm' => 'decimal:3',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function supplierLinks()
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function priceListItems()
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function costLayers()
    {
        return $this->hasMany(CostLayer::class);
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


