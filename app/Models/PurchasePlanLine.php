<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchasePlanLine extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'planned_qty' => 'decimal:3',
        'ordered_qty' => 'decimal:3',
        'required_date' => 'date',
    ];

    public function purchasePlan()
    {
        return $this->belongsTo(PurchasePlan::class);
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

    public function purchaseOrderLines()
    {
        return $this->hasMany(PurchaseOrderLine::class, 'source_plan_line_id');
    }

    /**
     * Get the remaining quantity that can still be ordered.
     */
    public function getRemainingQtyAttribute(): float
    {
        return max(0, (float) $this->planned_qty - (float) $this->ordered_qty);
    }
}
