<?php

namespace App\Models;

use App\Domain\Documents\StateMachine\Definitions\InvoiceStates;
use App\Domain\Documents\StateMachine\DocumentStateMachineDefinition;
use App\Enums\Documents\InvoiceStatus;
use App\Traits\Auditable;
use App\Traits\DocumentStateMachine;
use App\Traits\HasAccessLevelScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
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
    protected $auditable = ['status', 'total_amount'];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'grn_value_base' => 'decimal:4',
        'ppv_amount' => 'decimal:2',
        'payment_method' => \App\Enums\PaymentMethod::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (PurchaseInvoice $invoice): void {
            $invoice->status ??= InvoiceStatus::DRAFT->value;
        });
    }

    protected static function stateMachineDefinition(): DocumentStateMachineDefinition
    {
        return InvoiceStates::definition();
    }

    public function statusEnum(): InvoiceStatus
    {
        return InvoiceStatus::from($this->status);
    }

    public function purchaseOrders()
    {
        return $this->belongsToMany(PurchaseOrder::class, 'purchase_invoice_purchase_order');
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

    public function bankAccount()
    {
        return $this->belongsTo(PartnerBankAccount::class, 'partner_bank_account_id');
    }

    public function inventoryTransaction()
    {
        return $this->belongsTo(InventoryTransaction::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseInvoiceLine::class)->orderBy('line_number');
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

    /**
     * Purchase invoices don't have a sales person column.
     */
    public static function getSalesPersonColumn(): ?string
    {
        return null;
    }
}
