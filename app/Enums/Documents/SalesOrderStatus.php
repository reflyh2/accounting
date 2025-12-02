<?php

namespace App\Enums\Documents;

enum SalesOrderStatus: string
{
    case DRAFT = 'draft';
    case QUOTE = 'quote';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_DELIVERED = 'partially_delivered';
    case DELIVERED = 'delivered';
    case CLOSED = 'closed';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::QUOTE => 'Quote',
            self::CONFIRMED => 'Confirmed',
            self::PARTIALLY_DELIVERED => 'Partially Delivered',
            self::DELIVERED => 'Delivered',
            self::CLOSED => 'Closed',
            self::CANCELED => 'Canceled',
        };
    }
}


