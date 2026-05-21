<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_base' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'line_total' => 'decimal:2',
        'line_total_base' => 'decimal:4',
        'grn_value_base' => 'decimal:4',
        'ppv_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function goodsReceiptLine()
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Polymorphic pointer back to the obligation row this PI line was
     * generated from (BookingLine or SalesInvoiceCost). NULL for normal
     * manual PI lines that don't originate from a settled obligation.
     */
    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
