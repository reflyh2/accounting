<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDeposit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'deposit_date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'refunded_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function advanceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'advance_account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function companyBankAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(SupplierDepositConsumption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'global_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForPartner($query, int $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }
}
