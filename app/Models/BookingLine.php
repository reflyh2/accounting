<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function pool()
    {
        return $this->belongsTo(ResourcePool::class, 'resource_pool_id');
    }

    public function occurrence()
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function assignedInstance()
    {
        return $this->belongsTo(ResourceInstance::class, 'resource_instance_id');
    }

    public function resources()
    {
        return $this->hasMany(BookingLineResource::class);
    }
}

