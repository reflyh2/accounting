<?php

namespace App\Models;

use App\Domain\Documents\StateMachine\Definitions\PurchaseOrderStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Traits\Auditable;
use App\Traits\DocumentStateMachine;
use App\Traits\HasAccessLevelScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;
    use DocumentStateMachine;
    use Auditable;
    use HasAccessLevelScope;

    protected $guarded = [];

    /**
     * Fields to audit for this model.
     */
    protected $auditable = ['status', 'total_amount', 'approved_at', 'canceled_at'];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'canceled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $model): void {
            $model->status ??= PurchaseOrderStatus::DRAFT->value;
        });
    }

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return PurchaseOrderStates::definition();
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

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_number');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by', 'global_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by', 'global_id');
    }

    public function canceler()
    {
        return $this->belongsTo(User::class, 'canceled_by', 'global_id');
    }

    public function statusEnum(): PurchaseOrderStatus
    {
        return PurchaseOrderStatus::from($this->status);
    }

    public function invoices()
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    /**
     * Purchase orders don't have a sales person column.
     */
    public static function getSalesPersonColumn(): ?string
    {
        return null;
    }
}


