<?php

namespace App\Enums;

/**
 * Standard sales channels used across the application.
 * Used in: PriceList, PriceListTarget, SalesOrder
 */
enum SalesChannel: string
{
    case WEB = 'web';
    case POS = 'pos';
    case MARKETPLACE = 'marketplace';
    case B2B = 'b2b';

    public function label(): string
    {
        return match ($this) {
            self::WEB => 'Web',
            self::POS => 'POS',
            self::MARKETPLACE => 'Marketplace',
            self::B2B => 'B2B',
        };
    }

    /**
     * Get all channels as key => label array for forms.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $channel) => [$channel->value => $channel->label()])
            ->toArray();
    }
}
