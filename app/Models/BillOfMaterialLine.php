<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillOfMaterialLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::addGlobalScope('userBomLines', function ($builder) {
            if (Auth::check()) {
                $builder->whereHas('billOfMaterial');
            }
        });
    }

    public function billOfMaterial()
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    public function componentProduct()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function componentProductVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'component_product_variant_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function getTotalQuantityAttribute()
    {
        // Calculate total quantity needed including scrap
        $scrapFactor = 1 + ($this->scrap_percentage / 100);
        return $this->quantity_per * $scrapFactor;
    }
}
