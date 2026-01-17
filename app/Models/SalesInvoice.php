<?php

namespace App\Models;

use App\Domain\Documents\StateMachine\Definitions\SalesInvoiceStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Enums\Documents\InvoiceStatus;
use App\Traits\Auditable;
use App\Traits\DocumentStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;
    use DocumentStateMachine;
    use Auditable;

    protected $guarded = [];

    /**
     * Fields to audit for this model.
     */
    protected $auditable = ['status', 'total_amount'];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'delivery_value_base' => 'decimal:4',
        'revenue_variance' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesInvoice $invoice): void {
            $invoice->status ??= InvoiceStatus::DRAFT->value;
        });
    }

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return SalesInvoiceStates::definition();
    }

    public function statusEnum(): InvoiceStatus
    {
        return InvoiceStatus::from($this->status);
    }

    public function salesOrders()
    {
        return $this->belongsToMany(SalesOrder::class, 'sales_invoice_sales_order');
    }

    /**
     * Check if this is a direct invoice (no linked Sales Orders).
     */
    public function isDirectInvoice(): bool
    {
        return $this->salesOrders()->count() === 0;
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

    public function companyBankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function lines()
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('line_number');
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

    public function externalDebt()
    {
        return $this->belongsTo(ExternalDebt::class);
    }

    public function costs()
    {
        return $this->hasMany(SalesInvoiceCost::class);
    }
}
