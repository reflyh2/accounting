<?php

namespace App\Services\Sales;

use App\Enums\Documents\SalesOrderStatus;
use App\Exceptions\SalesOrderException;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Uom;
use App\Services\Catalog\PricingService;
use App\Services\Inventory\UomConversionService;
use App\Services\Tax\TaxService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesService
{
    private const MONEY_SCALE = 2;
    private const COST_SCALE = 4;
    private const QTY_SCALE = 3;

    public function __construct(
        private readonly UomConversionService $uomConverter,
        private readonly PricingService $pricingService,
        private readonly TaxService $taxService,
    ) {
    }

    public function create(array $payload, ?Authenticatable $actor = null): SalesOrder
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            $branch = $this->resolveBranch($payload['branch_id'] ?? null);
            $companyId = $branch->branchGroup?->company_id;

            if (!$companyId) {
                throw new SalesOrderException('Cabang belum terhubung dengan perusahaan.');
            }

            $this->assertCompanyMatch($payload['company_id'] ?? null, $companyId);

            $customer = $this->resolveCustomer((int) $payload['partner_id'], $companyId);
            $priceList = $this->resolvePriceList($payload['price_list_id'] ?? null, $companyId);
            $currencyId = $payload['currency_id'] ?? $priceList?->currency_id;

            if (!$currencyId || !Currency::query()->whereKey($currencyId)->exists()) {
                throw new SalesOrderException('Mata uang tidak valid.');
            }

            $orderDate = Carbon::parse($payload['order_date']);

            $salesOrder = SalesOrder::create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'partner_id' => $customer->id,
                'price_list_id' => $priceList?->id,
                'currency_id' => $currencyId,
                'order_number' => $this->generateOrderNumber($companyId, $branch->id, $orderDate),
                'order_date' => $orderDate,
                'expected_delivery_date' => isset($payload['expected_delivery_date'])
                    ? Carbon::parse($payload['expected_delivery_date'])
                    : null,
                'quote_valid_until' => isset($payload['quote_valid_until'])
                    ? Carbon::parse($payload['quote_valid_until'])
                    : null,
                'customer_reference' => $payload['customer_reference'] ?? null,
                'sales_channel' => $payload['sales_channel'] ?? null,
                'payment_terms' => $payload['payment_terms'] ?? null,
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'reserve_stock' => (bool) ($payload['reserve_stock'] ?? false),
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $context = [
                'company_id' => $companyId,
                'partner_id' => $customer->id,
                'price_list_id' => $priceList?->id,
                'date' => $orderDate,
                'channel' => $payload['sales_channel'] ?? null,
            ];

            $totals = $this->persistLines(
                $salesOrder,
                $payload['lines'] ?? [],
                $branch,
                $context,
                (bool) ($payload['reserve_stock'] ?? false)
            );

            $salesOrder->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
            ]);

            return $salesOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'priceList',
                'lines.variant.product',
                'lines.uom',
                'lines.baseUom',
                'lines.reservationLocation',
            ]);
        });
    }

    public function update(SalesOrder $salesOrder, array $payload, ?Authenticatable $actor = null): SalesOrder
    {
        $actor ??= Auth::user();

        if (!in_array($salesOrder->status, [
            SalesOrderStatus::DRAFT->value,
            SalesOrderStatus::QUOTE->value,
        ], true)) {
            throw new SalesOrderException('Hanya Sales Order dengan status Draft atau Quote yang dapat diperbarui.');
        }

        return DB::transaction(function () use ($salesOrder, $payload, $actor) {
            $branch = $this->resolveBranch($payload['branch_id'] ?? null);
            $companyId = $branch->branchGroup?->company_id;

            if (!$companyId) {
                throw new SalesOrderException('Cabang belum terhubung dengan perusahaan.');
            }

            $this->assertCompanyMatch($payload['company_id'] ?? null, $companyId);

            $customer = $this->resolveCustomer((int) $payload['partner_id'], $companyId);
            $priceList = $this->resolvePriceList($payload['price_list_id'] ?? null, $companyId);
            $currencyId = $payload['currency_id'] ?? $priceList?->currency_id;

            if (!$currencyId || !Currency::query()->whereKey($currencyId)->exists()) {
                throw new SalesOrderException('Mata uang tidak valid.');
            }

            $orderDate = Carbon::parse($payload['order_date']);

            $salesOrder->update([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'partner_id' => $customer->id,
                'price_list_id' => $priceList?->id,
                'currency_id' => $currencyId,
                'order_date' => $orderDate,
                'expected_delivery_date' => isset($payload['expected_delivery_date'])
                    ? Carbon::parse($payload['expected_delivery_date'])
                    : null,
                'quote_valid_until' => isset($payload['quote_valid_until'])
                    ? Carbon::parse($payload['quote_valid_until'])
                    : null,
                'customer_reference' => $payload['customer_reference'] ?? null,
                'sales_channel' => $payload['sales_channel'] ?? null,
                'payment_terms' => $payload['payment_terms'] ?? null,
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'reserve_stock' => (bool) ($payload['reserve_stock'] ?? false),
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $salesOrder->lines()->delete();

            $context = [
                'company_id' => $companyId,
                'partner_id' => $customer->id,
                'price_list_id' => $priceList?->id,
                'date' => $orderDate,
                'channel' => $payload['sales_channel'] ?? null,
            ];

            $totals = $this->persistLines(
                $salesOrder,
                $payload['lines'] ?? [],
                $branch,
                $context,
                (bool) ($payload['reserve_stock'] ?? false)
            );

            $salesOrder->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
            ]);

            return $salesOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'priceList',
                'lines.variant.product',
                'lines.uom',
                'lines.baseUom',
                'lines.reservationLocation',
            ]);
        });
    }

    public function delete(SalesOrder $salesOrder): void
    {
        if (!in_array($salesOrder->status, [
            SalesOrderStatus::DRAFT->value,
            SalesOrderStatus::QUOTE->value,
        ], true)) {
            throw new SalesOrderException('Hanya Sales Order draft atau quote yang dapat dihapus.');
        }

        DB::transaction(function () use ($salesOrder) {
            if ($salesOrder->reservation_applied_at) {
                $this->releaseReservation($salesOrder);
            }

            $salesOrder->lines()->delete();
            $salesOrder->delete();
        });
    }

    public function quote(SalesOrder $salesOrder, ?Authenticatable $actor = null): SalesOrder
    {
        $actor ??= Auth::user();

        if ($salesOrder->status !== SalesOrderStatus::DRAFT->value) {
            throw new SalesOrderException('Sales Order harus dalam status Draft sebelum dikirimkan sebagai Quote.');
        }

        $salesOrder->transitionTo(SalesOrderStatus::QUOTE, $actor);

        return $salesOrder->refresh();
    }

    public function confirm(SalesOrder $salesOrder, ?Authenticatable $actor = null): SalesOrder
    {
        $actor ??= Auth::user();

        if (!in_array($salesOrder->status, [
            SalesOrderStatus::DRAFT->value,
            SalesOrderStatus::QUOTE->value,
        ], true)) {
            throw new SalesOrderException('Sales Order harus Draft atau Quote sebelum dikonfirmasi.');
        }

        $salesOrder->transitionTo(SalesOrderStatus::CONFIRMED, $actor);

        if ($salesOrder->reserve_stock) {
            $this->applyReservation($salesOrder);
        }

        return $salesOrder->refresh();
    }

    public function cancel(
        SalesOrder $salesOrder,
        ?Authenticatable $actor = null,
        ?string $reason = null
    ): SalesOrder {
        $actor ??= Auth::user();

        if ($salesOrder->reservation_applied_at) {
            $this->releaseReservation($salesOrder);
        }

        $salesOrder->transitionTo(SalesOrderStatus::CANCELED, $actor);

        $salesOrder->update([
            'canceled_at' => now(),
            'canceled_by' => $actor?->getAuthIdentifier(),
            'canceled_reason' => $reason,
        ]);

        return $salesOrder->refresh();
    }

    public function applyReservation(SalesOrder $salesOrder): SalesOrder
    {
        if (!$salesOrder->reserve_stock) {
            throw new SalesOrderException('Reservasi stok tidak diaktifkan untuk Sales Order ini.');
        }

        if ($salesOrder->reservation_applied_at) {
            return $salesOrder;
        }

        $salesOrder->loadMissing([
            'lines.variant.product',
            'lines.baseUom',
            'lines.uom',
            'lines.reservationLocation.branch.branchGroup.company',
            'branch.branchGroup.company',
        ]);

        $company = $salesOrder->branch->branchGroup?->company;
        $strictness = strtolower((string) ($company?->reservation_strictness ?? 'soft'));

        DB::transaction(function () use ($salesOrder, $strictness) {
            foreach ($salesOrder->lines as $line) {
                if (!$line->reservation_location_id) {
                    throw new SalesOrderException('Lokasi pemenuhan wajib diisi untuk melakukan reservasi stok.');
                }

                $reservedQty = $this->reserveInventory(
                    $line,
                    $line->reservation_location_id,
                    $strictness
                );

                $line->quantity_reserved_base = $this->roundQuantity($reservedQty);
                $line->quantity_reserved = $this->roundQuantity(
                    $this->convertBaseToOrdered($line, $reservedQty)
                );
                $line->save();
            }

            $salesOrder->update([
                'reservation_applied_at' => now(),
            ]);
        });

        return $salesOrder->refresh();
    }

    public function releaseReservation(SalesOrder $salesOrder): SalesOrder
    {
        if (!$salesOrder->reservation_applied_at) {
            return $salesOrder;
        }

        $salesOrder->loadMissing('lines');

        DB::transaction(function () use ($salesOrder) {
            foreach ($salesOrder->lines as $line) {
                if ($line->quantity_reserved_base <= 0) {
                    continue;
                }

                /** @var InventoryItem|null $item */
                $item = InventoryItem::query()
                    ->where('product_variant_id', $line->product_variant_id)
                    ->where('location_id', $line->reservation_location_id)
                    ->whereNull('lot_id')
                    ->whereNull('serial_id')
                    ->lockForUpdate()
                    ->first();

                if ($item) {
                    $item->qty_reserved = $this->roundQuantity(
                        max(0, (float) $item->qty_reserved - (float) $line->quantity_reserved_base)
                    );
                    $item->save();
                }

                $line->update([
                    'quantity_reserved' => 0,
                    'quantity_reserved_base' => 0,
                ]);
            }

            $salesOrder->update([
                'reservation_released_at' => now(),
                'reservation_applied_at' => null,
            ]);
        });

        return $salesOrder->refresh();
    }

    public function allowedStatuses(SalesOrder $salesOrder, ?Authenticatable $actor = null): array
    {
        $actor ??= Auth::user();

        return collect(
            $salesOrder->allowedDocumentStatuses($actor)
        )->map(fn (SalesOrderStatus $status) => $status->value)
            ->values()
            ->toArray();
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{subtotal: float, tax_total: float, total_amount: float}
     */
    private function persistLines(
        SalesOrder $salesOrder,
        array $lines,
        Branch $branch,
        array $context,
        bool $reserveStock
    ): array {
        if (empty($lines)) {
            throw new SalesOrderException('Minimal satu baris barang harus diisi.');
        }

        $lineNumber = 1;
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $variant = ProductVariant::with([
                'product.companies:id',
                'product.taxCategory',
                'product',
                'uom',
            ])->findOrFail($line['product_variant_id']);

            if (!$variant->product->companies->pluck('id')->contains($branch->branchGroup?->company_id)) {
                throw new SalesOrderException('Produk tidak tersedia untuk perusahaan ini.');
            }

            $orderedUom = Uom::findOrFail($line['uom_id']);
            if ((int) $orderedUom->company_id !== (int) $branch->branchGroup?->company_id) {
                throw new SalesOrderException('Satuan tidak valid untuk perusahaan ini.');
            }

            $baseUom = $variant->uom;
            if ((int) $baseUom->company_id !== (int) $branch->branchGroup?->company_id) {
                throw new SalesOrderException('Satuan dasar varian tidak valid.');
            }

            $quantity = $this->roundQuantity((float) $line['quantity']);
            if ($quantity <= 0) {
                throw new SalesOrderException('Jumlah harus lebih besar dari nol.');
            }

            try {
                $quantityBase = $this->roundQuantity(
                    $this->uomConverter->convert($quantity, $orderedUom->id, $baseUom->id)
                );
            } catch (RuntimeException $exception) {
                throw new SalesOrderException($exception->getMessage(), previous: $exception);
            }

            $unitPrice = $this->resolveUnitPrice(
                $variant,
                $orderedUom->id,
                $quantity,
                $context,
                $line['unit_price'] ?? null
            );

            $taxRate = $this->resolveTaxRate(
                $variant,
                $context,
                $line['tax_rate'] ?? null
            );

            $lineSubtotal = $this->roundMoney($quantity * $unitPrice);
            $lineTax = $this->roundMoney($lineSubtotal * ($taxRate / 100));
            $lineTotal = $this->roundMoney($lineSubtotal + $lineTax);

            $reservationLocationId = $this->resolveReservationLocation(
                $line['reservation_location_id'] ?? null,
                $branch->id,
                $reserveStock
            );

            $salesOrder->lines()->create([
                'line_number' => $lineNumber++,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'description' => $line['description'] ?? $variant->product->name,
                'uom_id' => $orderedUom->id,
                'base_uom_id' => $baseUom->id,
                'quantity' => $quantity,
                'quantity_base' => $quantityBase,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
                'requested_delivery_date' => isset($line['requested_delivery_date'])
                    ? Carbon::parse($line['requested_delivery_date'])
                    : null,
                'reservation_location_id' => $reservationLocationId,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        $subtotal = $this->roundMoney($subtotal);
        $taxTotal = $this->roundMoney($taxTotal);

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total_amount' => $this->roundMoney($subtotal + $taxTotal),
        ];
    }

    private function resolveCustomer(int $partnerId, int $companyId): Partner
    {
        /** @var Partner $partner */
        $partner = Partner::with(['roles', 'companies:id'])->findOrFail($partnerId);

        $isCustomer = $partner->roles->pluck('role')->contains('customer');
        $belongsToCompany = $partner->companies->pluck('id')->contains($companyId);

        if (!$isCustomer) {
            throw new SalesOrderException('Partner terpilih bukan pelanggan.');
        }

        if (!$belongsToCompany) {
            throw new SalesOrderException('Pelanggan tidak terdaftar pada perusahaan ini.');
        }

        return $partner;
    }

    private function resolveBranch(?int $branchId): Branch
    {
        if (!$branchId) {
            throw new SalesOrderException('Cabang wajib dipilih.');
        }

        return Branch::with('branchGroup')->findOrFail($branchId);
    }

    private function resolvePriceList(?int $priceListId, int $companyId): ?PriceList
    {
        if (!$priceListId) {
            return null;
        }

        /** @var PriceList $priceList */
        $priceList = PriceList::with('company')->findOrFail($priceListId);

        if ($priceList->company_id && (int) $priceList->company_id !== $companyId) {
            throw new SalesOrderException('Daftar harga tidak sesuai dengan perusahaan.');
        }

        return $priceList;
    }

    private function resolveUnitPrice(
        ProductVariant $variant,
        int $uomId,
        float $quantity,
        array $context,
        ?float $explicitPrice
    ): float {
        if ($explicitPrice !== null) {
            return $this->roundCost($explicitPrice);
        }

        $quote = $this->pricingService->quote(
            $variant->product_id,
            $variant->id,
            $uomId,
            $quantity,
            $context
        );

        return $this->roundCost((float) ($quote['price'] ?? 0));
    }

    private function resolveTaxRate(
        ProductVariant $variant,
        array $context,
        ?float $explicitRate
    ): float {
        if ($explicitRate !== null) {
            return round($explicitRate, 2);
        }

        $quote = $this->taxService->quote(
            $variant->product,
            $context
        );

        return round((float) ($quote['rate'] ?? 0), 2);
    }

    private function assertCompanyMatch(?int $requestedCompanyId, int $branchCompanyId): void
    {
        if (!$requestedCompanyId) {
            throw new SalesOrderException('Perusahaan wajib dipilih.');
        }

        if ((int) $requestedCompanyId !== (int) $branchCompanyId) {
            throw new SalesOrderException('Cabang tidak sesuai dengan perusahaan yang dipilih.');
        }
    }

    private function generateOrderNumber(int $companyId, int $branchId, Carbon $orderDate): string
    {
        $config = config('sales.numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'SO');
        $sequencePadding = (int) ($config['sequence_padding'] ?? 5);
        $sequence = str_pad(
            (string) $this->nextSequence($branchId, $orderDate),
            $sequencePadding,
            '0',
            STR_PAD_LEFT
        );

        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $orderDate->format('y');

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function nextSequence(int $branchId, Carbon $orderDate): int
    {
        $latest = SalesOrder::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('order_date', $orderDate->year)
            ->orderByDesc('order_number')
            ->value('order_number');

        if (!$latest) {
            return 1;
        }

        $segments = explode('.', $latest);
        $last = (int) (end($segments) ?: 0);

        return $last + 1;
    }

    private function roundMoney(float $value): float
    {
        return round($value, self::MONEY_SCALE);
    }

    private function roundCost(float $value): float
    {
        return round($value, self::COST_SCALE);
    }

    private function roundQuantity(float $value): float
    {
        return round($value, self::QTY_SCALE);
    }

    private function resolveReservationLocation(?int $locationId, int $branchId, bool $reserveStock): ?int
    {
        if (!$locationId) {
            if ($reserveStock) {
                throw new SalesOrderException('Lokasi pemenuhan wajib dipilih saat reservasi stok aktif.');
            }

            return null;
        }

        /** @var Location $location */
        $location = Location::with('branch')->findOrFail($locationId);

        if ((int) $location->branch_id !== $branchId) {
            throw new SalesOrderException('Lokasi tidak berasal dari cabang yang sama.');
        }

        return $location->id;
    }

    private function reserveInventory(SalesOrderLine $line, int $locationId, string $strictness): float
    {
        $item = InventoryItem::query()
            ->where('product_variant_id', $line->product_variant_id)
            ->where('location_id', $locationId)
            ->whereNull('lot_id')
            ->whereNull('serial_id')
            ->lockForUpdate()
            ->first();

        if (!$item) {
            $item = InventoryItem::create([
                'product_variant_id' => $line->product_variant_id,
                'location_id' => $locationId,
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
            ]);

            $item = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->first();
        }

        $available = (float) $item->qty_on_hand - (float) $item->qty_reserved;
        $requested = (float) $line->quantity_base;
        $tolerance = (float) config('sales.reservation.tolerance', 0.0005);

        if ($available + $tolerance < $requested && $strictness === 'hard') {
            throw new SalesOrderException('Persediaan tidak mencukupi untuk melakukan reservasi.');
        }

        $reservedQty = min($requested, max(0.0, $available));

        if ($reservedQty <= 0) {
            return 0.0;
        }

        $item->qty_reserved = $this->roundQuantity((float) $item->qty_reserved + $reservedQty);
        $item->save();

        return $reservedQty;
    }

    private function convertBaseToOrdered(SalesOrderLine $line, float $baseQty): float
    {
        $base = (float) $line->quantity_base;
        $ordered = (float) $line->quantity;

        if ($base <= 0) {
            return $baseQty;
        }

        $ratio = $ordered / $base;

        return $baseQty * $ratio;
    }
}


