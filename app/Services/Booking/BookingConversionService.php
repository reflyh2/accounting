<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\FulfillmentMode;
use App\Exceptions\BookingConversionException;
use App\Models\Booking;
use App\Models\BookingLine;
use App\Models\SalesOrder;
use App\Services\Sales\SalesService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingConversionService
{
    public function __construct(private SalesService $salesService) {}

    /**
     * Convert a Booking into a Sales Order.
     *
     * Mode-specific behaviour:
     *  - SELF_OPERATED: one SO line per BookingLine, no cost row at SO time.
     *  - RESELLER: one SO line per BookingLine; supplier_cost mirrored as a SalesOrderCost.
     *  - AGENT: each BookingLine becomes TWO SO lines — commission_revenue + passthrough_supplier.
     *
     * Idempotent: re-running on a booking already converted returns the existing SO.
     *
     * @param  array<string,mixed>  $overrides  Optional payload overrides (order_date, payment_term_id, etc.)
     */
    public function convertToSalesOrder(
        Booking $booking,
        array $overrides = [],
        ?Authenticatable $actor = null
    ): SalesOrder {
        $actor ??= Auth::user();

        if ($booking->converted_sales_order_id) {
            $existing = SalesOrder::find($booking->converted_sales_order_id);
            if ($existing) {
                return $existing;
            }
        }

        $this->assertConvertible($booking);

        return DB::transaction(function () use ($booking, $overrides, $actor) {
            $booking->loadMissing(['lines.product', 'lines.productVariant.uom', 'lines.product.defaultUom']);

            $mode = $this->resolveMode($booking);
            $this->assertModeInvariants($booking, $mode);

            $payload = $this->buildSalesOrderPayload($booking, $mode, $overrides);

            $salesOrder = $this->salesService->create($payload, $actor);

            $this->attachResellerCosts($salesOrder, $booking, $mode);

            $booking->update([
                'converted_sales_order_id' => $salesOrder->id,
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            return $salesOrder->fresh([
                'lines.variant.product',
                'lines.uom',
                'costs.costItem',
            ]);
        });
    }

    private function assertConvertible(Booking $booking): void
    {
        $allowed = [
            BookingStatus::CONFIRMED->value,
            BookingStatus::CHECKED_IN->value,
            BookingStatus::CHECKED_OUT->value,
            BookingStatus::COMPLETED->value,
        ];

        if (! in_array($booking->status, $allowed, true)) {
            throw new BookingConversionException(
                'Booking harus dalam status Confirmed atau lebih lanjut sebelum dikonversi ke Sales Order.'
            );
        }

        if ($booking->lines()->count() === 0) {
            throw new BookingConversionException('Booking tidak memiliki baris untuk dikonversi.');
        }
    }

    private function resolveMode(Booking $booking): FulfillmentMode
    {
        $value = $booking->fulfillment_mode instanceof FulfillmentMode
            ? $booking->fulfillment_mode
            : FulfillmentMode::tryFrom((string) $booking->fulfillment_mode);

        return $value ?? FulfillmentMode::SELF_OPERATED;
    }

    private function assertModeInvariants(Booking $booking, FulfillmentMode $mode): void
    {
        foreach ($booking->lines as $line) {
            $missing = [];

            if ($mode === FulfillmentMode::RESELLER) {
                if (empty($line->supplier_partner_id)) {
                    $missing[] = 'supplier_partner_id';
                }
                if ((float) $line->supplier_cost <= 0) {
                    $missing[] = 'supplier_cost';
                }
            }

            if ($mode === FulfillmentMode::AGENT) {
                if (empty($line->supplier_partner_id)) {
                    $missing[] = 'supplier_partner_id';
                }
                $sum = (float) $line->commission_amount + (float) $line->passthrough_amount;
                if (abs($sum - (float) $line->amount) > 0.01) {
                    throw new BookingConversionException(sprintf(
                        'Baris booking #%d: commission (%.2f) + passthrough (%.2f) tidak sama dengan amount (%.2f).',
                        $line->id,
                        $line->commission_amount,
                        $line->passthrough_amount,
                        $line->amount
                    ));
                }
            }

            if (! empty($missing)) {
                throw new BookingConversionException(sprintf(
                    'Baris booking #%d (mode %s) belum lengkap: %s.',
                    $line->id,
                    $mode->value,
                    implode(', ', $missing)
                ));
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSalesOrderPayload(Booking $booking, FulfillmentMode $mode, array $overrides): array
    {
        // Source the SO's order_date from the booking's booked_at (the user-chosen
        // booking date), so a backdated booking produces a backdated SO. Caller can
        // still override via $overrides['order_date'] when needed.
        $orderDate = $overrides['order_date']
            ?? ($booking->booked_at ? $booking->booked_at->toDateString() : Carbon::now()->toDateString());

        $lines = [];
        foreach ($booking->lines as $line) {
            $lines = array_merge($lines, $this->buildLinePayloads($line, $mode));
        }

        return array_merge([
            'company_id' => $booking->company_id,
            'branch_id' => $booking->branch_id,
            'partner_id' => $booking->partner_id,
            'currency_id' => $booking->currency_id,
            'order_date' => $orderDate,
            'expected_delivery_date' => $orderDate,
            'customer_reference' => $booking->booking_number,
            'sales_channel' => $booking->source_channel ?: 'booking',
            'reserve_stock' => false,
            'notes' => trim(sprintf(
                "Dibuat dari Booking %s.\n%s",
                $booking->booking_number,
                $booking->notes ?? ''
            )),
            'lines' => $lines,
        ], $overrides);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildLinePayloads(BookingLine $line, FulfillmentMode $mode): array
    {
        $description = $this->describeLine($line);
        $uomId = $this->resolveUomId($line);

        $base = [
            'product_id' => $line->product_id,
            'product_variant_id' => $line->product_variant_id,
            'uom_id' => $uomId,
            'description' => $description,
            'tax_rate' => 0,
            'discount_rate' => 0,
            'start_date' => $line->start_datetime,
            'end_date' => $line->end_datetime,
            'resource_pool_id' => $line->resource_pool_id,
            'booking_line_id' => $line->id,
        ];

        if ($mode === FulfillmentMode::AGENT) {
            $payloads = [];
            if ((float) $line->commission_amount > 0) {
                $payloads[] = array_merge($base, [
                    'quantity' => 1,
                    'unit_price' => (float) $line->commission_amount,
                    'description' => 'Komisi: '.$description,
                    'revenue_role' => 'commission_revenue',
                ]);
            }
            if ((float) $line->passthrough_amount > 0) {
                $payloads[] = array_merge($base, [
                    'quantity' => 1,
                    'unit_price' => (float) $line->passthrough_amount,
                    'description' => 'Pass-through: '.$description,
                    'revenue_role' => 'passthrough_supplier',
                ]);
            }

            return $payloads;
        }

        return [array_merge($base, [
            'quantity' => max(1, (int) $line->qty),
            'unit_price' => (float) $line->unit_price,
            'revenue_role' => 'gross_revenue',
        ])];
    }

    /**
     * Pick the UoM for the resulting SO line. Mirrors the SalesOrderForm fallback chain:
     * variant's UoM → product's default UoM. Throw a clear error if neither is set so
     * the user gets a meaningful message instead of a leaked ModelNotFoundException → 404.
     */
    private function resolveUomId(BookingLine $line): int
    {
        $line->loadMissing(['productVariant.uom', 'product.defaultUom']);

        $uomId = $line->productVariant?->uom_id
            ?? $line->productVariant?->uom?->id
            ?? $line->product?->default_uom_id
            ?? $line->product?->defaultUom?->id;

        if (! $uomId) {
            throw new BookingConversionException(sprintf(
                'Produk "%s" pada baris booking #%d tidak memiliki satuan default. Set satuan default produk atau pilih varian yang punya satuan sebelum mengonversi.',
                $line->product?->name ?? 'Unknown',
                $line->id
            ));
        }

        return (int) $uomId;
    }

    private function describeLine(BookingLine $line): string
    {
        $product = $line->product?->name ?? 'Item';
        $meta = is_array($line->meta) ? $line->meta : [];
        $subtype = $line->booking?->booking_subtype ?? null;

        if ($subtype === 'flight' && isset($meta['origin'], $meta['destination'])) {
            $when = $line->start_datetime?->format('Y-m-d') ?? '';
            $passenger = $meta['passenger_name'] ?? '';
            $pnr = $meta['pnr'] ?? '';

            return trim(sprintf(
                '%s %s→%s %s%s%s',
                $product,
                $meta['origin'],
                $meta['destination'],
                $when,
                $passenger ? " — {$passenger}" : '',
                $pnr ? " (PNR {$pnr})" : ''
            ));
        }

        if ($subtype === 'hotel' && isset($meta['check_in'], $meta['check_out'])) {
            return trim(sprintf(
                '%s %s s/d %s',
                $product,
                $meta['check_in'],
                $meta['check_out']
            ));
        }

        if ($subtype === 'car_rental' && isset($meta['pickup_datetime'], $meta['return_datetime'])) {
            return trim(sprintf(
                '%s %s s/d %s',
                $product,
                $meta['pickup_datetime'],
                $meta['return_datetime']
            ));
        }

        return $product;
    }

    /**
     * For RESELLER mode, mirror each line's supplier_cost into a SalesOrderCost row
     * so it flows into the existing cost-attribution pipeline at invoice time.
     */
    private function attachResellerCosts(SalesOrder $salesOrder, Booking $booking, FulfillmentMode $mode): void
    {
        if ($mode !== FulfillmentMode::RESELLER) {
            return;
        }

        $totalSupplierCost = 0.0;
        foreach ($booking->lines as $line) {
            $totalSupplierCost += (float) $line->supplier_cost;
        }

        if ($totalSupplierCost <= 0) {
            return;
        }

        $salesOrder->costs()->create([
            'cost_item_id' => null,
            'description' => 'Biaya supplier dari Booking '.$booking->booking_number,
            'amount' => $totalSupplierCost,
            'currency_id' => $salesOrder->currency_id,
            'exchange_rate' => $salesOrder->exchange_rate ?? 1,
        ]);
    }
}
