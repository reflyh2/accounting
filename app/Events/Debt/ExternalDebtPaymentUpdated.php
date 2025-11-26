<?php

namespace App\Events\Debt;

use App\Models\ExternalDebtPayment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExternalDebtPaymentUpdated
{
    use Dispatchable, SerializesModels;

    public ExternalDebtPayment $payment;

    public function __construct(ExternalDebtPayment $payment)
    {
        $this->payment = $payment;
    }
}


