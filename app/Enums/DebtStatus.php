<?php

namespace App\Enums;

enum DebtStatus: string
{
    case PENDING = 'pending';
    case OPEN = 'open';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
    case DEFAULTED = 'defaulted';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Persetujuan',
            self::OPEN => 'Aktif',
            self::PARTIALLY_PAID => 'Dibayar Sebagian',
            self::PAID => 'Lunas',
            self::OVERDUE => 'Jatuh Tempo',
            self::CANCELLED => 'Dibatalkan',
            self::DEFAULTED => 'Gagal Bayar',
            self::CLOSED => 'Selesai',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PAID => 'bg-green-100 text-green-800',
            self::OVERDUE => 'bg-orange-100 text-orange-800',
            self::CANCELLED, self::DEFAULTED => 'bg-red-100 text-red-800',
            self::PARTIALLY_PAID => 'bg-teal-100 text-teal-800',
            self::CLOSED => 'bg-gray-100 text-gray-800',
            self::OPEN => 'bg-blue-100 text-blue-800',
            self::PENDING => 'bg-yellow-100 text-yellow-800',
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


