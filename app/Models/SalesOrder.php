<?php

namespace App\Models;

use App\Domain\Documents\StateMachine\Definitions\SalesOrderStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Enums\Documents\SalesOrderStatus;
use App\Traits\DocumentStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory;
    use SoftDeletes;
    use DocumentStateMachine;

    protected $guarded = [];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'quote_valid_until' => 'date',
        'reserve_stock' => 'boolean',
        'reservation_applied_at' => 'datetime',
        'reservation_released_at' => 'datetime',
        'canceled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesOrder $model): void {
            $model->status ??= SalesOrderStatus::DRAFT->value;
        });
    }

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return SalesOrderStates::definition();
    }

    public function statusEnum(): SalesOrderStatus
    {
        return SalesOrderStatus::from($this->status);
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

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function lines()
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('line_number');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }

    public function canceler()
    {
        return $this->belongsTo(User::class, 'canceled_by', 'global_id');
    }
}


