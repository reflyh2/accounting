<?php

namespace App\Enums;

/**
 * FulfillmentMode Enum
 *
 * Defines how a booking's economics are recognized:
 *  - SELF_OPERATED: business owns the underlying asset (own hotel/plane/car).
 *    Revenue at sale; costs accumulate in a CostPool and are allocated at period close.
 *  - RESELLER: business buys wholesale from a supplier and resells (principal).
 *    Per-line supplier cost is captured and posted to a clearing liability at COGS time.
 *  - AGENT: business resells someone else's inventory (net method).
 *    Customer charge splits into commission_revenue + passthrough_supplier;
 *    passthrough hits a supplier liability, not revenue.
 */
enum FulfillmentMode: string
{
    case SELF_OPERATED = 'self_operated';
    case RESELLER = 'reseller';
    case AGENT = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::SELF_OPERATED => 'Operasional Sendiri',
            self::RESELLER => 'Reseller (Principal)',
            self::AGENT => 'Agen (Net)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SELF_OPERATED => 'Aset milik sendiri — biaya operasional dialokasikan dari cost pool',
            self::RESELLER => 'Beli dari supplier dan jual kembali — biaya supplier per baris',
            self::AGENT => 'Jual produk pihak ketiga — pendapatan komisi saja',
        };
    }

    /**
     * Whether this mode requires per-line supplier cost capture.
     */
    public function requiresSupplierCost(): bool
    {
        return $this === self::RESELLER;
    }

    /**
     * Whether this mode requires per-line supplier identification (vendor partner).
     */
    public function requiresSupplierPartner(): bool
    {
        return in_array($this, [self::RESELLER, self::AGENT], true);
    }

    /**
     * Whether revenue recognition uses the net (commission) method.
     */
    public function usesNetMethod(): bool
    {
        return $this === self::AGENT;
    }

    /**
     * Whether this mode resolves COGS via cost-pool allocation.
     */
    public function usesPoolAllocation(): bool
    {
        return $this === self::SELF_OPERATED;
    }

    /**
     * @return array<string,string> for select inputs
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
