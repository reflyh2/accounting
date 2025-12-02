<?php

namespace App\Enums\Documents;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case SENT = 'sent';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CLOSED = 'closed';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
            self::SENT => 'Sent',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED => 'Received',
            self::CLOSED => 'Closed',
            self::CANCELED => 'Canceled',
        };
    }
}


