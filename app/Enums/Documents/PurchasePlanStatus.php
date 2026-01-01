<?php

namespace App\Enums\Documents;

enum PurchasePlanStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Dikonfirmasi',
            self::CLOSED => 'Ditutup',
            self::CANCELLED => 'Dibatalkan',
        };
    }
}
