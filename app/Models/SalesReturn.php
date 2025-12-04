<?php

namespace App\Models;

use App\Domain\Documents\StateMachine\Definitions\SalesReturnStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Traits\DocumentStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use HasFactory;
    use SoftDeletes;
    use DocumentStateMachine;

    protected $guarded = [];

    protected $casts = [
        'return_date' => 'date',
        'total_quantity' => 'decimal:3',
        'total_value' => 'decimal:4',
        'total_value_base' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return SalesReturnStates::definition();
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesDelivery()
    {
        return $this->belongsTo(SalesDelivery::class);
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

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function inventoryTransaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    public function lines()
    {
        return $this->hasMany(SalesReturnLine::class)->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by', 'global_id');
    }
}
