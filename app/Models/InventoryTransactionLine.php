<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransactionLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'effect' => 'string',
    ];

    public function transaction()
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
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

    public function costLayers()
    {
        return $this->hasMany(CostLayer::class);
    }

    public function costConsumptions()
    {
        return $this->hasMany(InventoryCostConsumption::class, 'inventory_transaction_line_id');
    }
}


