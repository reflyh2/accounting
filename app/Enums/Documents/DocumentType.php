<?php

namespace App\Enums\Documents;

enum DocumentType: string
{
    case SALES_ORDER = 'sales_order';
    case SALES_DELIVERY = 'sales_delivery';
    case SALES_INVOICE = 'sales_invoice';

    public function label(): string
    {
        return match ($this) {
            self::SALES_ORDER => 'Sales Order',
            self::SALES_DELIVERY => 'Surat Jalan',
            self::SALES_INVOICE => 'Faktur Penjualan',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }
}
