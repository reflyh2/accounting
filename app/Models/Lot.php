<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lot extends Model
{
    protected $guarded = [];

    protected $casts = [
        'mfg_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function goodsReceiptLines()
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}


