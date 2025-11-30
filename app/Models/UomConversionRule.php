<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UomConversionRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'numerator' => 'decimal:6',
        'denominator' => 'decimal:6',
        'factor' => 'decimal:12',
        'avg_weight_g' => 'decimal:6',
        'density_kg_per_l' => 'decimal:6',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'decimal_places' => 'integer',
    ];

    public function fromUom()
    {
        return $this->belongsTo(Uom::class, 'from_uom_id');
    }

    public function toUom()
    {
        return $this->belongsTo(Uom::class, 'to_uom_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}


