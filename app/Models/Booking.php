<?php

namespace App\Models;

use App\Enums\FulfillmentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $guarded = [];

    protected $casts = [
        'booked_at' => 'datetime',
        'held_until' => 'datetime',
        'deposit_amount' => 'decimal:2',
        'deposit_received_amount' => 'decimal:2',
        'deposit_received_at' => 'datetime',
        'deposit_applied_at' => 'datetime',
        'fulfillment_mode' => FulfillmentMode::class,
    ];

    public function depositAppliedToInvoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'deposit_applied_to_invoice_id');
    }

    public function depositCompanyBankAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'deposit_company_bank_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BookingLine::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function convertedSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_sales_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'global_id');
    }
}
