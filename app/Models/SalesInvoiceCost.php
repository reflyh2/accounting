<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesInvoiceCost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function salesOrderCost()
    {
        return $this->belongsTo(SalesOrderCost::class);
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
