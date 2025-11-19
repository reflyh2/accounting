<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalDebtPaymentDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function payment()
    {
        return $this->belongsTo(ExternalDebtPayment::class, 'external_debt_payment_id');
    }

    public function externalDebt()
    {
        return $this->belongsTo(ExternalDebt::class);
    }
}


