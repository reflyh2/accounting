<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InternalDebtPaymentDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'primary_currency_amount' => 'float',
    ];

    public function payment()
    {
        return $this->belongsTo(InternalDebtPayment::class, 'internal_debt_payment_id');
    }

    public function internalDebt()
    {
        return $this->belongsTo(InternalDebt::class);
    }
}


