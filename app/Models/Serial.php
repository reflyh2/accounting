<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Serial extends Model
{
    protected $guarded = [];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function goodsReceiptLines()
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}


