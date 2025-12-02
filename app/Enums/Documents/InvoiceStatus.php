<?php

namespace App\Enums\Documents;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::POSTED => 'Posted',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::CANCELED => 'Canceled',
        };
    }
}


