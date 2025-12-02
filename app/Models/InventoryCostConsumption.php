<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCostConsumption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    public function line()
    {
        return $this->belongsTo(InventoryTransactionLine::class, 'inventory_transaction_line_id');
    }

    public function costLayer()
    {
        return $this->belongsTo(CostLayer::class);
    }
}


