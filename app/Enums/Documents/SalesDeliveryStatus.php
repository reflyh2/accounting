<?php

namespace App\Enums\Documents;

enum SalesDeliveryStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::POSTED => 'Posted',
        };
    }
}


