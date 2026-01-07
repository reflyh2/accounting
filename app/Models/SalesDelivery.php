<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesDelivery extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'delivery_date' => 'date',
        'posted_at' => 'datetime',
        'total_quantity' => 'decimal:3',
        'total_amount' => 'decimal:2',
        'total_cogs' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    public function salesOrders()
    {
        return $this->belongsToMany(SalesOrder::class, 'sales_delivery_sales_order')
            ->withTimestamps();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function inventoryTransaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    public function lines()
    {
        return $this->hasMany(SalesDeliveryLine::class)->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


