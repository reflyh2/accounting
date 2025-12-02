<?php

namespace App\Models;

use App\Domain\Documents\StateMachine\Definitions\GoodsReceiptStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Traits\DocumentStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use HasFactory;
    use SoftDeletes;
    use DocumentStateMachine;

    protected $guarded = [];

    protected $casts = [
        'receipt_date' => 'date',
        'posted_at' => 'datetime',
        'total_quantity' => 'decimal:3',
        'total_value' => 'decimal:4',
        'total_value_base' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return GoodsReceiptStates::definition();
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
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
        return $this->hasMany(GoodsReceiptLine::class)->orderBy('id');
    }
}


