<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'deposit_required' => 'decimal:2',
        'supplier_cost' => 'decimal:2',
        'supplier_cost_base' => 'decimal:4',
        'commission_amount' => 'decimal:2',
        'passthrough_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(ResourcePool::class, 'resource_pool_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function assignedInstance(): BelongsTo
    {
        return $this->belongsTo(ResourceInstance::class, 'resource_instance_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_partner_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(BookingLineResource::class);
    }
}
