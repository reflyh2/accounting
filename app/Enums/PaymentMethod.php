<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
    case CHECK = 'cek';
    case GIRO = 'giro';
    case CREDIT_CARD = 'credit_card';
    case QRIS = 'qris';
    case PAYPAL = 'paypal';
    case MIDTRANS = 'midtrans';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai',
            self::TRANSFER => 'Transfer Bank',
            self::CHECK => 'Cek',
            self::GIRO => 'Giro',
            self::CREDIT_CARD => 'Kartu Kredit',
            self::QRIS => 'QRIS',
            self::PAYPAL => 'Paypal',
            self::MIDTRANS => 'Midtrans',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::CASH => 'bg-blue-100 text-blue-800',
            self::TRANSFER => 'bg-blue-100 text-blue-800',
            self::CHECK => 'bg-blue-100 text-blue-800',
            self::GIRO => 'bg-blue-100 text-blue-800',
            self::CREDIT_CARD => 'bg-blue-100 text-blue-800',
            self::QRIS => 'bg-green-100 text-green-800',
            self::PAYPAL => 'bg-indigo-100 text-indigo-800',
            self::MIDTRANS => 'bg-purple-100 text-purple-800',
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
