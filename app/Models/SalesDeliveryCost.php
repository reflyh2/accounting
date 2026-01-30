<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDeliveryCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function salesDelivery()
    {
        return $this->belongsTo(SalesDelivery::class);
    }

    public function costItem()
    {
        return $this->belongsTo(CostItem::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
