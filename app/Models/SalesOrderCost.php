<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:4',
        'percentage' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
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
