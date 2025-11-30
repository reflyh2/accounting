<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case CHECK = 'cek';
    case GIRO = 'giro';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai',
            self::TRANSFER => 'Transfer Bank',
            self::CHECK => 'Cek',
            self::GIRO => 'Giro',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::CASH => 'bg-blue-100 text-blue-800',
            self::TRANSFER => 'bg-blue-100 text-blue-800',
            self::CHECK => 'bg-blue-100 text-blue-800',
            self::GIRO => 'bg-blue-100 text-blue-800',
        };
    }

    public static function styles(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = [
                'label' => $case->label(),
                'class' => $case->badgeClasses(),
            ];
        }
        return $map;
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


