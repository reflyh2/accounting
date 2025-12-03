<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_base' => 'decimal:3',
        'quantity_invoiced' => 'decimal:3',
        'quantity_invoiced_base' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'quantity_returned_base' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'unit_cost_base' => 'decimal:6',
        'line_total' => 'decimal:2',
        'line_total_base' => 'decimal:4',
        'amount_invoiced' => 'decimal:2',
        'amount_returned' => 'decimal:2',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class);
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

    public function invoiceLines()
    {
        return $this->hasMany(PurchaseInvoiceLine::class);
    }

    public function purchaseReturnLines()
    {
        return $this->hasMany(PurchaseReturnLine::class);
    }
}


