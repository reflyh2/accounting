<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostLayer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'qty_remaining' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }

    public function serial()
    {
        return $this->belongsTo(Serial::class);
    }

    public function transactionLine()
    {
        return $this->belongsTo(InventoryTransactionLine::class, 'inventory_transaction_line_id');
    }

    public function consumptions()
    {
        return $this->hasMany(InventoryCostConsumption::class);
    }
}


