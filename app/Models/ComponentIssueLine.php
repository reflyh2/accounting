<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ComponentIssueLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::addGlobalScope('userComponentIssueLines', function ($builder) {
            if (Auth::check()) {
                $builder->whereHas('componentIssue');
            }
        });
    }

    public function componentIssue()
    {
        return $this->belongsTo(ComponentIssue::class);
    }

    public function bomLine()
    {
        return $this->belongsTo(BillOfMaterialLine::class, 'bom_line_id');
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

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    public function serial()
    {
        return $this->belongsTo(Serial::class);
    }

    public function componentScraps()
    {
        return $this->hasMany(ComponentScrap::class);
    }
}
