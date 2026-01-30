<?php

namespace App\Enums;

enum ShippingProviderType: string
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';

    public function label(): string
    {
        return match ($this) {
            self::INTERNAL => 'Internal (Kurir Sendiri)',
            self::EXTERNAL => 'Eksternal (Jasa Pengiriman)',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::INTERNAL => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            self::EXTERNAL => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        };
    }

    public static function options(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->label();
        }

        return $map;
    }
}
