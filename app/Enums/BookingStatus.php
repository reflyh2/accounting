<?php

namespace App\Enums;

enum BookingStatus: string
{
    case HOLD = 'hold';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case CHECKED_OUT = 'checked_out';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
    case NO_SHOW = 'no_show';

    public static function blockingStatuses(): array
    {
        return [
            self::CONFIRMED->value,
            self::CHECKED_IN->value,
            self::CHECKED_OUT->value,
            self::COMPLETED->value,
        ];
    }
}

