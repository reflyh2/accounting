<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_base' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity_reserved' => 'decimal:3',
        'quantity_reserved_base' => 'decimal:3',
        'quantity_delivered' => 'decimal:3',
        'quantity_delivered_base' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'quantity_returned_base' => 'decimal:3',
        'quantity_invoiced' => 'decimal:3',
        'quantity_invoiced_base' => 'decimal:3',
        'requested_delivery_date' => 'date',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function baseUom()
    {
        return $this->belongsTo(Uom::class, 'base_uom_id');
    }

    public function reservationLocation()
    {
        return $this->belongsTo(Location::class, 'reservation_location_id');
    }

    public function deliveryLines()
    {
        return $this->hasMany(SalesDeliveryLine::class);
    }
}


