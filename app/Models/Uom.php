<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function productVariants()
    {
        return $this->hasMany(ProductVariant::class, 'uom_id');
    }

    public function defaultProducts()
    {
        return $this->hasMany(Product::class, 'default_uom_id');
    }

    public function conversionsFrom()
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    public function conversionsTo()
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }

    public function conversionRulesFrom()
    {
        return $this->hasMany(UomConversionRule::class, 'from_uom_id');
    }

    public function conversionRulesTo()
    {
        return $this->hasMany(UomConversionRule::class, 'to_uom_id');
    }
}


