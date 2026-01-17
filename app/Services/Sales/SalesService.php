<?php

namespace App\Services\Sales;

use App\Enums\AccountingEventCode;
use App\Enums\Documents\SalesDeliveryStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Exceptions\SalesOrderException;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Partner;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Models\SalesOrderCost;
use App\Models\SalesOrderLine;
use App\Models\Uom;
use App\Services\Catalog\PricingService;
use App\Services\Accounting\AccountingEventBuilder;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Inventory\DTO\IssueDTO;
use App\Services\Inventory\DTO\IssueLineDTO;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\UomConversionService;
use App\Services\Tax\TaxService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Domain\Accounting\DTO\AccountingEntry;
use Throwable;
use RuntimeException;

use App\Services\Booking\BookingService;
use App\Services\Booking\DTO\HoldBookingDTO;
use App\Services\Booking\DTO\BookingLineDTO;

class SalesService
{
    private const MONEY_SCALE = 2;
    private const COST_SCALE = 4;
    private const QTY_SCALE = 3;
    private const QTY_TOLERANCE = 0.0005;

    public function __construct(
        private readonly UomConversionService $uomConverter,
        private readonly PricingService $pricingService,
        private readonly TaxService $taxService,
        private readonly InventoryService $inventoryService,
        private readonly AccountingEventBus $accountingEventBus,
        private readonly BookingService $bookingService,
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
                'payment_method' => $payload['payment_method'] ?? null,
                'company_bank_account_id' => $payload['company_bank_account_id'] ?? null,
                'sales_person_id' => $payload['sales_person_id'] ?? $actor?->getAuthIdentifier(),
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

            // Persist costs if provided
            $this->persistCosts($salesOrder, $payload['costs'] ?? []);

            return $salesOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'priceList',
                'lines.variant.product',
                'lines.uom',
                'lines.baseUom',
                'lines.reservationLocation',
                'costs.costItem',
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
                'payment_method' => $payload['payment_method'] ?? null,
                'company_bank_account_id' => $payload['company_bank_account_id'] ?? null,
                'sales_person_id' => $payload['sales_person_id'] ?? $salesOrder->sales_person_id,
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

            // Persist costs if provided
            $this->persistCosts($salesOrder, $payload['costs'] ?? []);

            return $salesOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'priceList',
                'lines.variant.product',
                'lines.uom',
                'lines.baseUom',
                'lines.reservationLocation',
                'costs.costItem',
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

        $this->createBookingsForOrder($salesOrder);

        return $salesOrder->refresh();
    }

    private function createBookingsForOrder(SalesOrder $salesOrder): void
    {
        $salesOrder->loadMissing(['lines.product.rentalPolicy']);

        $bookingLines = $salesOrder->lines->filter(function (SalesOrderLine $line) {
            return $line->resource_pool_id && $line->start_date && $line->end_date;
        });

        if ($bookingLines->isEmpty()) {
            return;
        }

        // Check availability for all booking lines before creating
        $this->checkBookingAvailability($bookingLines);

        // Group by booking type
        $groupedLines = $bookingLines->groupBy(function (SalesOrderLine $line) {
            return $line->product->rentalPolicy ? 'rental' : 'accommodation';
        });

        foreach ($groupedLines as $type => $lines) {
            // Collect the SO line IDs in order for later linking
            $soLineIds = $lines->pluck('id')->values()->toArray();

            $linesDto = $lines->map(function (SalesOrderLine $line) {
                return new BookingLineDTO(
                    productId: $line->product_id,
                    resourcePoolId: $line->resource_pool_id,
                    start: $line->start_date,
                    end: $line->end_date,
                    qty: (int) $line->quantity,
                    unitPrice: (float) $line->unit_price,
                    productVariantId: $line->product_variant_id,
                );
            })->values()->toArray();

            $dto = new HoldBookingDTO(
                companyId: $salesOrder->company_id,
                branchId: $salesOrder->branch_id,
                partnerId: $salesOrder->partner_id,
                currencyId: $salesOrder->currency_id,
                bookingType: $type,
                heldUntil: null,
                depositAmount: 0,
                sourceChannel: 'sales_order',
                notes: 'Generated from Sales Order ' . $salesOrder->order_number,
                lines: $linesDto
            );

            $booking = $this->bookingService->hold($dto);
            $this->bookingService->confirm($booking->id);

            // Link booking lines back to SO lines
            $this->linkBookingLinesToOrder($booking, $soLineIds);
        }
    }

    /**
     * Check availability for all booking lines before creating bookings.
     *
     * @throws SalesOrderException
     */
    private function checkBookingAvailability(\Illuminate\Support\Collection $bookingLines): void
    {
        $availabilityService = app(\App\Services\Booking\AvailabilityService::class);

        foreach ($bookingLines as $line) {
            $result = $availabilityService->searchPoolAvailability(
                $line->resource_pool_id,
                $line->start_date,
                $line->end_date,
                (int) $line->quantity
            );

            if ($result->availableQty < $line->quantity) {
                $productName = $line->product->name ?? 'Product';
                throw new SalesOrderException(
                    "Kapasitas tidak tersedia untuk {$productName}. " .
                    "Tersedia: {$result->availableQty}, Dibutuhkan: {$line->quantity}"
                );
            }
        }
    }

    /**
     * Link created booking lines to SO lines.
     */
    private function linkBookingLinesToOrder(\App\Models\Booking $booking, array $soLineIds): void
    {
        $booking->loadMissing('lines');

        // Match booking lines to SO lines by order
        foreach ($booking->lines as $index => $bookingLine) {
            if (isset($soLineIds[$index])) {
                SalesOrderLine::where('id', $soLineIds[$index])
                    ->update(['booking_line_id' => $bookingLine->id]);
            }
        }
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

    public function postDelivery(SalesOrder $salesOrder, array $payload, ?Authenticatable $actor = null): SalesDelivery
    {
        $actor ??= Auth::user();

        if (!in_array($salesOrder->status, [
            SalesOrderStatus::CONFIRMED->value,
            SalesOrderStatus::PARTIALLY_DELIVERED->value,
        ], true)) {
            throw new SalesOrderException('Sales Order belum siap untuk dikirim.');
        }

        $salesOrder->loadMissing([
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'lines.reservationLocation',
            'partner',
            'branch.branchGroup.company',
            'currency',
        ]);

        if (!$salesOrder->lines->count()) {
            throw new SalesOrderException('Sales Order tidak memiliki detail barang.');
        }

        if (empty($payload['lines']) || !is_array($payload['lines'])) {
            throw new SalesOrderException('Minimal satu baris pengiriman harus dipilih.');
        }

        if (!isset($payload['delivery_date'])) {
            throw new SalesOrderException('Tanggal pengiriman wajib diisi.');
        }

        if (!isset($payload['location_id'])) {
            throw new SalesOrderException('Lokasi pengiriman wajib dipilih.');
        }

        $deliveryDate = Carbon::parse($payload['delivery_date']);

        /** @var Location $location */
        $location = Location::with('branch.branchGroup.company')->findOrFail($payload['location_id']);

        if ((int) $location->branch_id !== (int) $salesOrder->branch_id) {
            throw new SalesOrderException('Lokasi pengiriman harus berasal dari cabang yang sama.');
        }

        $preparedLines = $this->prepareDeliveryLines($salesOrder, $payload['lines']);

        if (empty($preparedLines)) {
            throw new SalesOrderException('Jumlah pengiriman tidak valid.');
        }

        $valuationMethod = $payload['valuation_method'] ?? null;

        return DB::transaction(function () use (
            $salesOrder,
            $preparedLines,
            $location,
            $deliveryDate,
            $payload,
            $valuationMethod,
            $actor
        ) {
            $issueDto = new IssueDTO(
                $deliveryDate,
                $location->id,
                array_map(
                    fn (array $plan) => new IssueLineDTO(
                        $plan['order_line']->product_variant_id,
                        $plan['order_line']->base_uom_id,
                        $plan['quantity_base'],
                    ),
                    $preparedLines
                ),
                sourceType: SalesOrder::class,
                sourceId: $salesOrder->id,
                notes: $payload['notes'] ?? null,
                valuationMethod: $valuationMethod,
            );

            $result = $this->inventoryService->issue($issueDto);

            $deliveryNumber = $this->generateDeliveryNumber(
                $salesOrder->company_id,
                $salesOrder->branch_id,
                $deliveryDate
            );

            $delivery = SalesDelivery::create([
                'sales_order_id' => $salesOrder->id,
                'company_id' => $salesOrder->company_id,
                'branch_id' => $salesOrder->branch_id,
                'partner_id' => $salesOrder->partner_id,
                'currency_id' => $salesOrder->currency_id,
                'location_id' => $location->id,
                'delivery_number' => $deliveryNumber,
                'status' => SalesDeliveryStatus::DRAFT->value,
                'delivery_date' => $deliveryDate,
                'exchange_rate' => $salesOrder->exchange_rate,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $totalQuantityBase = 0.0;
            $totalAmount = 0.0;
            $totalCogs = 0.0;

            foreach ($preparedLines as $index => $plan) {
                $transactionLine = $result->transaction->lines[$index] ?? null;
                $unitCostBase = $transactionLine?->unit_cost !== null
                    ? (float) $transactionLine->unit_cost
                    : 0.0;

                $cogsTotal = $this->roundCost($unitCostBase * $plan['quantity_base']);

                $delivery->lines()->create([
                    'sales_order_line_id' => $plan['order_line']->id,
                    'product_id' => $plan['order_line']->product_id,
                    'product_variant_id' => $plan['order_line']->product_variant_id,
                    'description' => $plan['order_line']->description,
                    'uom_id' => $plan['order_line']->uom_id,
                    'base_uom_id' => $plan['order_line']->base_uom_id,
                    'quantity' => $plan['quantity'],
                    'quantity_base' => $plan['quantity_base'],
                    'unit_price' => $plan['unit_price'],
                    'unit_cost_base' => $unitCostBase,
                    'line_total' => $plan['line_total'],
                    'cogs_total' => $cogsTotal,
                ]);

                $orderLine = $plan['order_line'];
                $orderLine->quantity_delivered = $this->roundQuantity(
                    (float) $orderLine->quantity_delivered + $plan['quantity']
                );
                $orderLine->quantity_delivered_base = $this->roundQuantity(
                    (float) $orderLine->quantity_delivered_base + $plan['quantity_base']
                );

                if ($orderLine->quantity_reserved_base > 0) {
                    $orderLine->quantity_reserved_base = $this->roundQuantity(
                        max(0, (float) $orderLine->quantity_reserved_base - $plan['quantity_base'])
                    );
                    $orderLine->quantity_reserved = $orderLine->quantity_reserved_base > 0
                        ? $this->convertBaseToOrdered($orderLine, (float) $orderLine->quantity_reserved_base)
                        : 0;
                    $this->consumeReservation($orderLine, $plan['quantity_base']);
                }

                $orderLine->save();

                $totalQuantityBase += $plan['quantity_base'];
                $totalAmount += $plan['line_total'];
                $totalCogs += $cogsTotal;
            }

            $delivery->update([
                'inventory_transaction_id' => $result->transaction->id,
                'status' => SalesDeliveryStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_amount' => $this->roundMoney($totalAmount),
                'total_cogs' => $this->roundCost($totalCogs),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $result->transaction->update([
                'source_type' => SalesDelivery::class,
                'source_id' => $delivery->id,
            ]);

            $salesOrder->load('lines');
            $this->syncSalesOrderDeliveryStatus($salesOrder, $actor);

            $delivery->load([
                'salesOrder.partner',
                'salesOrder.branch.branchGroup.company',
                'lines.variant',
                'lines.uom',
                'lines.baseUom',
                'currency',
                'location',
            ]);

            $this->dispatchDeliveryEvent($delivery, $salesOrder, $totalCogs, $actor);

            return $delivery;
        });
    }

    /**
     * Create a delivery from multiple Sales Orders.
     */
    public function postDeliveryFromMultipleSOs(array $payload, ?Authenticatable $actor = null): SalesDelivery
    {
        $actor ??= Auth::user();

        $salesOrderIds = $payload['sales_order_ids'] ?? [];
        if (empty($salesOrderIds)) {
            throw new SalesOrderException('Minimal satu Sales Order harus dipilih.');
        }

        $salesOrders = SalesOrder::with([
            'lines.variant.product',
            'lines.uom',
            'lines.baseUom',
            'lines.reservationLocation',
            'partner',
            'branch.branchGroup.company',
            'currency',
        ])->whereIn('id', $salesOrderIds)->get();

        if ($salesOrders->isEmpty()) {
            throw new SalesOrderException('Sales Order tidak ditemukan.');
        }

        // Validate all SOs belong to same customer, company, branch, and currency
        $partnerId = $salesOrders->first()->partner_id;
        $companyId = $salesOrders->first()->company_id;
        $branchId = $salesOrders->first()->branch_id;
        $currencyId = $salesOrders->first()->currency_id;
        $exchangeRate = $salesOrders->first()->exchange_rate;

        foreach ($salesOrders as $so) {
            if ($so->partner_id !== $partnerId) {
                throw new SalesOrderException('Semua Sales Order harus memiliki customer yang sama.');
            }
            if ($so->company_id !== $companyId) {
                throw new SalesOrderException('Semua Sales Order harus memiliki perusahaan yang sama.');
            }
            if ($so->branch_id !== $branchId) {
                throw new SalesOrderException('Semua Sales Order harus memiliki cabang yang sama.');
            }
            if (!in_array($so->status, [
                SalesOrderStatus::CONFIRMED->value,
                SalesOrderStatus::PARTIALLY_DELIVERED->value,
            ], true)) {
                throw new SalesOrderException("Sales Order {$so->order_number} belum siap untuk dikirim.");
            }
        }

        if (empty($payload['lines']) || !is_array($payload['lines'])) {
            throw new SalesOrderException('Minimal satu baris pengiriman harus dipilih.');
        }

        if (!isset($payload['delivery_date'])) {
            throw new SalesOrderException('Tanggal pengiriman wajib diisi.');
        }

        if (!isset($payload['location_id'])) {
            throw new SalesOrderException('Lokasi pengiriman wajib dipilih.');
        }

        $deliveryDate = Carbon::parse($payload['delivery_date']);

        /** @var Location $location */
        $location = Location::with('branch.branchGroup.company')->findOrFail($payload['location_id']);

        if ((int) $location->branch_id !== (int) $branchId) {
            throw new SalesOrderException('Lokasi pengiriman harus berasal dari cabang yang sama.');
        }

        // Build SO lines indexed by ID for quick lookup
        $allSOLines = collect();
        foreach ($salesOrders as $so) {
            foreach ($so->lines as $line) {
                $allSOLines[$line->id] = $line;
            }
        }

        // Prepare delivery lines
        $preparedLines = [];
        foreach ($payload['lines'] as $lineData) {
            $soLineId = $lineData['sales_order_line_id'] ?? null;
            $quantity = (float) ($lineData['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            /** @var SalesOrderLine|null $soLine */
            $soLine = $allSOLines[$soLineId] ?? null;
            if (!$soLine) {
                throw new SalesOrderException("Baris Sales Order (ID: {$soLineId}) tidak ditemukan.");
            }

            $remaining = max(0, (float) $soLine->quantity - (float) $soLine->quantity_delivered);
            if ($quantity > $remaining + self::QTY_TOLERANCE) {
                throw new SalesOrderException("Jumlah pengiriman untuk {$soLine->description} melebihi sisa.");
            }

            try {
                $quantityBase = $this->roundQuantity(
                    $this->uomConverter->convert($quantity, $soLine->uom_id, $soLine->base_uom_id)
                );
            } catch (RuntimeException $exception) {
                throw new SalesOrderException($exception->getMessage(), previous: $exception);
            }

            $lineTotal = $this->roundMoney($quantity * (float) $soLine->unit_price);

            $preparedLines[] = [
                'order_line' => $soLine,
                'quantity' => $this->roundQuantity($quantity),
                'quantity_base' => $this->roundQuantity($quantityBase),
                'unit_price' => (float) $soLine->unit_price,
                'line_total' => $lineTotal,
            ];
        }

        if (empty($preparedLines)) {
            throw new SalesOrderException('Jumlah pengiriman tidak valid.');
        }

        $valuationMethod = $payload['valuation_method'] ?? null;

        return DB::transaction(function () use (
            $salesOrders,
            $preparedLines,
            $location,
            $deliveryDate,
            $payload,
            $valuationMethod,
            $actor,
            $partnerId,
            $companyId,
            $branchId,
            $currencyId,
            $exchangeRate
        ) {
            $issueDto = new IssueDTO(
                $deliveryDate,
                $location->id,
                array_map(
                    fn (array $plan) => new IssueLineDTO(
                        $plan['order_line']->product_variant_id,
                        $plan['order_line']->base_uom_id,
                        $plan['quantity_base'],
                    ),
                    $preparedLines
                ),
                sourceType: SalesDelivery::class,
                sourceId: 0, // Will update after creation
                notes: $payload['notes'] ?? null,
                valuationMethod: $valuationMethod,
            );

            $result = $this->inventoryService->issue($issueDto);

            $deliveryNumber = $this->generateDeliveryNumber($companyId, $branchId, $deliveryDate);

            $delivery = SalesDelivery::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'partner_id' => $partnerId,
                'currency_id' => $currencyId,
                'location_id' => $location->id,
                'delivery_number' => $deliveryNumber,
                'status' => SalesDeliveryStatus::DRAFT->value,
                'delivery_date' => $deliveryDate,
                'exchange_rate' => $exchangeRate,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            // Attach all related SOs
            $delivery->salesOrders()->attach($salesOrders->pluck('id')->toArray());

            $totalQuantityBase = 0.0;
            $totalAmount = 0.0;
            $totalCogs = 0.0;

            foreach ($preparedLines as $index => $plan) {
                $transactionLine = $result->transaction->lines[$index] ?? null;
                $unitCostBase = $transactionLine?->unit_cost !== null
                    ? (float) $transactionLine->unit_cost
                    : 0.0;

                $cogsTotal = $this->roundCost($unitCostBase * $plan['quantity_base']);

                $delivery->lines()->create([
                    'sales_order_line_id' => $plan['order_line']->id,
                    'product_id' => $plan['order_line']->product_id,
                    'product_variant_id' => $plan['order_line']->product_variant_id,
                    'description' => $plan['order_line']->description,
                    'uom_id' => $plan['order_line']->uom_id,
                    'base_uom_id' => $plan['order_line']->base_uom_id,
                    'quantity' => $plan['quantity'],
                    'quantity_base' => $plan['quantity_base'],
                    'unit_price' => $plan['unit_price'],
                    'unit_cost_base' => $unitCostBase,
                    'line_total' => $plan['line_total'],
                    'cogs_total' => $cogsTotal,
                ]);

                $orderLine = $plan['order_line'];
                $orderLine->quantity_delivered = $this->roundQuantity(
                    (float) $orderLine->quantity_delivered + $plan['quantity']
                );
                $orderLine->quantity_delivered_base = $this->roundQuantity(
                    (float) $orderLine->quantity_delivered_base + $plan['quantity_base']
                );

                if ($orderLine->quantity_reserved_base > 0) {
                    $orderLine->quantity_reserved_base = $this->roundQuantity(
                        max(0, (float) $orderLine->quantity_reserved_base - $plan['quantity_base'])
                    );
                    $orderLine->quantity_reserved = $orderLine->quantity_reserved_base > 0
                        ? $this->convertBaseToOrdered($orderLine, (float) $orderLine->quantity_reserved_base)
                        : 0;
                    $this->consumeReservation($orderLine, $plan['quantity_base']);
                }

                $orderLine->save();

                $totalQuantityBase += $plan['quantity_base'];
                $totalAmount += $plan['line_total'];
                $totalCogs += $cogsTotal;
            }

            $delivery->update([
                'inventory_transaction_id' => $result->transaction->id,
                'status' => SalesDeliveryStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_amount' => $this->roundMoney($totalAmount),
                'total_cogs' => $this->roundCost($totalCogs),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $result->transaction->update([
                'source_type' => SalesDelivery::class,
                'source_id' => $delivery->id,
            ]);

            // Sync status for all affected SOs
            foreach ($salesOrders as $so) {
                $so->load('lines');
                $this->syncSalesOrderDeliveryStatus($so, $actor);
            }

            $delivery->load([
                'salesOrders.partner',
                'salesOrders.branch.branchGroup.company',
                'partner',
                'lines.variant',
                'lines.uom',
                'lines.baseUom',
                'currency',
                'location',
            ]);

            // Dispatch accounting event for first SO (for backwards compat)
            $firstSO = $salesOrders->first();
            $this->dispatchDeliveryEvent($delivery, $firstSO, $totalCogs, $actor);

            return $delivery;
        });
    }

    /**
     * Check if a sales delivery can be modified (not invoiced).
     */
    public function canModifyDelivery(SalesDelivery $delivery): bool
    {
        return $delivery->lines->every(fn ($line) => (float) $line->quantity_invoiced <= 0);
    }

    /**
     * Update an existing sales delivery.
     */
    public function updateSalesDelivery(SalesDelivery $delivery, array $payload, ?Authenticatable $actor = null): SalesDelivery
    {
        $actor ??= Auth::user();

        $delivery->loadMissing([
            'lines.salesOrderLine',
            'salesOrders.lines',
            'inventoryTransaction',
            'location.branch.branchGroup.company',
        ]);

        if (!$this->canModifyDelivery($delivery)) {
            throw new SalesOrderException('Pengiriman tidak dapat diubah karena sudah digunakan pada invoice.');
        }

        if (!isset($payload['location_id'])) {
            throw new SalesOrderException('Lokasi pengiriman wajib dipilih.');
        }

        if (!isset($payload['delivery_date'])) {
            throw new SalesOrderException('Tanggal pengiriman wajib diisi.');
        }

        $deliveryDate = Carbon::parse($payload['delivery_date']);

        /** @var Location $location */
        $location = Location::with('branch.branchGroup.company')->findOrFail($payload['location_id']);

        if ((int) $location->branch_id !== (int) $delivery->branch_id) {
            throw new SalesOrderException('Lokasi pengiriman harus berasal dari cabang yang sama.');
        }

        return DB::transaction(function () use (
            $delivery,
            $location,
            $deliveryDate,
            $payload,
            $actor
        ) {
            // Reverse existing inventory transaction if posted
            if ($delivery->inventory_transaction_id && $delivery->inventoryTransaction) {
                // First, reverse the SO line delivered quantities
                foreach ($delivery->lines as $sdLine) {
                    if ($sdLine->salesOrderLine) {
                        $soLine = $sdLine->salesOrderLine;
                        $soLine->quantity_delivered = $this->roundQuantity(
                            max(0, (float) $soLine->quantity_delivered - (float) $sdLine->quantity)
                        );
                        $soLine->quantity_delivered_base = $this->roundQuantity(
                            max(0, (float) $soLine->quantity_delivered_base - (float) $sdLine->quantity_base)
                        );
                        $soLine->save();
                    }
                }

                // Clear transaction reference
                $transaction = $delivery->inventoryTransaction;
                $delivery->inventory_transaction_id = null;
                $delivery->save();

                // Dispatch reversal accounting event
                $this->dispatchDeliveryReversalEvent($delivery, $actor);

                // Delete the inventory transaction (reverses inventory)
                $this->inventoryService->deleteTransaction($transaction);
            }

            // Delete existing lines
            $delivery->lines()->delete();

            // Get all related SOs
            $salesOrders = $delivery->salesOrders()->with([
                'lines.variant.product',
                'lines.uom',
                'lines.baseUom',
                'lines.reservationLocation',
            ])->get();

            // Build SO lines indexed by ID
            $allSOLines = collect();
            foreach ($salesOrders as $so) {
                foreach ($so->lines as $line) {
                    $allSOLines[$line->id] = $line;
                }
            }

            // Prepare new lines
            $preparedLines = [];
            foreach ($payload['lines'] ?? [] as $lineData) {
                $soLineId = $lineData['sales_order_line_id'] ?? null;
                $quantity = (float) ($lineData['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $soLine = $allSOLines[$soLineId] ?? null;
                if (!$soLine) {
                    throw new SalesOrderException("Baris Sales Order (ID: {$soLineId}) tidak ditemukan.");
                }

                $remaining = max(0, (float) $soLine->quantity - (float) $soLine->quantity_delivered);
                if ($quantity > $remaining + self::QTY_TOLERANCE) {
                    throw new SalesOrderException("Jumlah pengiriman untuk {$soLine->description} melebihi sisa.");
                }

                try {
                    $quantityBase = $this->roundQuantity(
                        $this->uomConverter->convert($quantity, $soLine->uom_id, $soLine->base_uom_id)
                    );
                } catch (RuntimeException $exception) {
                    throw new SalesOrderException($exception->getMessage(), previous: $exception);
                }

                $lineTotal = $this->roundMoney($quantity * (float) $soLine->unit_price);

                $preparedLines[] = [
                    'order_line' => $soLine,
                    'quantity' => $this->roundQuantity($quantity),
                    'quantity_base' => $this->roundQuantity($quantityBase),
                    'unit_price' => (float) $soLine->unit_price,
                    'line_total' => $lineTotal,
                ];
            }

            if (empty($preparedLines)) {
                throw new SalesOrderException('Jumlah pengiriman tidak valid.');
            }

            // Create new inventory issue
            $issueDto = new IssueDTO(
                $deliveryDate,
                $location->id,
                array_map(
                    fn (array $plan) => new IssueLineDTO(
                        $plan['order_line']->product_variant_id,
                        $plan['order_line']->base_uom_id,
                        $plan['quantity_base'],
                    ),
                    $preparedLines
                ),
                sourceType: SalesDelivery::class,
                sourceId: $delivery->id,
                notes: $payload['notes'] ?? null,
            );

            $result = $this->inventoryService->issue($issueDto);

            $totalQuantityBase = 0.0;
            $totalAmount = 0.0;
            $totalCogs = 0.0;

            foreach ($preparedLines as $index => $plan) {
                $transactionLine = $result->transaction->lines[$index] ?? null;
                $unitCostBase = $transactionLine?->unit_cost !== null
                    ? (float) $transactionLine->unit_cost
                    : 0.0;

                $cogsTotal = $this->roundCost($unitCostBase * $plan['quantity_base']);

                $delivery->lines()->create([
                    'sales_order_line_id' => $plan['order_line']->id,
                    'product_id' => $plan['order_line']->product_id,
                    'product_variant_id' => $plan['order_line']->product_variant_id,
                    'description' => $plan['order_line']->description,
                    'uom_id' => $plan['order_line']->uom_id,
                    'base_uom_id' => $plan['order_line']->base_uom_id,
                    'quantity' => $plan['quantity'],
                    'quantity_base' => $plan['quantity_base'],
                    'unit_price' => $plan['unit_price'],
                    'unit_cost_base' => $unitCostBase,
                    'line_total' => $plan['line_total'],
                    'cogs_total' => $cogsTotal,
                ]);

                // Update SO line delivered quantity
                $orderLine = $plan['order_line'];
                $orderLine->quantity_delivered = $this->roundQuantity(
                    (float) $orderLine->quantity_delivered + $plan['quantity']
                );
                $orderLine->quantity_delivered_base = $this->roundQuantity(
                    (float) $orderLine->quantity_delivered_base + $plan['quantity_base']
                );
                $orderLine->save();

                $totalQuantityBase += $plan['quantity_base'];
                $totalAmount += $plan['line_total'];
                $totalCogs += $cogsTotal;
            }

            $delivery->update([
                'location_id' => $location->id,
                'delivery_date' => $deliveryDate,
                'notes' => $payload['notes'] ?? null,
                'inventory_transaction_id' => $result->transaction->id,
                'status' => SalesDeliveryStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_amount' => $this->roundMoney($totalAmount),
                'total_cogs' => $this->roundCost($totalCogs),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            // Sync all SO statuses
            foreach ($salesOrders as $so) {
                $so->load('lines');
                $this->syncSalesOrderDeliveryStatus($so, $actor);
            }

            $delivery->load([
                'salesOrders.partner',
                'salesOrders.branch.branchGroup.company',
                'partner',
                'lines.variant',
                'lines.uom',
                'lines.baseUom',
                'currency',
                'location',
            ]);

            // Dispatch accounting event
            $firstSO = $salesOrders->first();
            $this->dispatchDeliveryEvent($delivery, $firstSO, $totalCogs, $actor);

            return $delivery;
        });
    }

    /**
     * Delete a sales delivery, reversing inventory and accounting entries.
     */
    public function deleteSalesDelivery(SalesDelivery $delivery, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        $delivery->loadMissing([
            'lines.salesOrderLine',
            'salesOrders',
            'inventoryTransaction',
            'location.branch.branchGroup.company',
        ]);

        if (!$this->canModifyDelivery($delivery)) {
            throw new SalesOrderException('Pengiriman tidak dapat dihapus karena sudah digunakan pada invoice.');
        }

        DB::transaction(function () use ($delivery, $actor) {
            // If posted, reverse inventory and accounting
            if ($delivery->status === SalesDeliveryStatus::POSTED->value) {
                // Reverse inventory transaction
                if ($delivery->inventoryTransaction) {
                    $transaction = $delivery->inventoryTransaction;
                    $delivery->inventory_transaction_id = null;
                    $delivery->save();

                    $this->inventoryService->deleteTransaction($transaction);
                }

                // Dispatch accounting reversal event
                $this->dispatchDeliveryReversalEvent($delivery, $actor);
            }

            // Restore SO line delivered quantities
            foreach ($delivery->lines as $sdLine) {
                if ($sdLine->salesOrderLine) {
                    $soLine = $sdLine->salesOrderLine;
                    $soLine->quantity_delivered = $this->roundQuantity(
                        max(0, (float) $soLine->quantity_delivered - (float) $sdLine->quantity)
                    );
                    $soLine->quantity_delivered_base = $this->roundQuantity(
                        max(0, (float) $soLine->quantity_delivered_base - (float) $sdLine->quantity_base)
                    );
                    $soLine->save();
                }
            }

            // Update SO statuses
            foreach ($delivery->salesOrders as $so) {
                $so->loadMissing('lines');
                $this->syncSalesOrderDeliveryStatus($so, $actor);
            }

            // Detach from pivot table
            $delivery->salesOrders()->detach();

            // Delete lines
            $delivery->lines()->delete();

            // Delete the delivery
            $delivery->delete();
        });
    }

    /**
     * Dispatch accounting reversal event for sales delivery deletion.
     */
    private function dispatchDeliveryReversalEvent(SalesDelivery $delivery, ?Authenticatable $actor = null): void
    {
        $cogsAmount = (float) $delivery->total_cogs;

        if ($cogsAmount <= 0) {
            return;
        }

        $currencyCode = $delivery->currency?->code ?? 'IDR';
        $salesOrderNumbers = $delivery->salesOrders->pluck('order_number')->toArray();

        $payload = new AccountingEventPayload(
            AccountingEventCode::SALES_DELIVERY_REVERSED,
            $delivery->company_id,
            $delivery->branch_id,
            'sales_delivery',
            $delivery->id,
            $delivery->delivery_number,
            $currencyCode,
            (float) $delivery->exchange_rate,
            CarbonImmutable::parse($delivery->delivery_date ?? now()),
            $actor?->getAuthIdentifier(),
            [
                'sales_order_numbers' => $salesOrderNumbers,
                'reversal' => true,
            ],
        );

        $normalizedAmount = $this->roundCost($cogsAmount);

        // Reverse the original entries: credit COGS, debit inventory
        $payload->setLines([
            AccountingEntry::debit('inventory', $normalizedAmount),
            AccountingEntry::credit('cost_of_goods_sold', $normalizedAmount),
        ]);

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
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
            $variant = null;
            $product = null;
            $baseUom = null;

            // Handle both products with and without variants
            if (!empty($line['product_variant_id'])) {
                $variant = ProductVariant::with([
                    'product.companies:id',
                    'product.taxCategory',
                    'product.defaultUom',
                    'product',
                    'uom',
                ])->findOrFail($line['product_variant_id']);
                $product = $variant->product;
                $baseUom = $variant->uom;
            } else {
                // Product without variant - load product directly
                $product = Product::with([
                    'companies:id',
                    'taxCategory',
                    'defaultUom',
                ])->findOrFail($line['product_id']);
                $baseUom = $product->defaultUom;
            }

            if (!$product->companies->pluck('id')->contains($branch->branchGroup?->company_id)) {
                throw new SalesOrderException('Produk tidak tersedia untuk perusahaan ini.');
            }

            $orderedUom = Uom::findOrFail($line['uom_id']);
            if ((int) $orderedUom->company_id !== (int) $branch->branchGroup?->company_id) {
                throw new SalesOrderException('Satuan tidak valid untuk perusahaan ini.');
            }

            if (!$baseUom) {
                throw new SalesOrderException('Produk tidak memiliki satuan dasar yang valid.');
            }

            if ((int) $baseUom->company_id !== (int) $branch->branchGroup?->company_id) {
                throw new SalesOrderException('Satuan dasar tidak valid untuk perusahaan ini.');
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

            $unitPrice = $this->resolveUnitPriceForProduct(
                $product,
                $variant,
                $orderedUom->id,
                $quantity,
                $context,
                $line['unit_price'] ?? null
            );

            $taxRate = $this->resolveTaxRateForProduct(
                $product,
                $context,
                $line['tax_rate'] ?? null
            );

            $discountRate = (float) ($line['discount_rate'] ?? 0);
            $lineGross = $this->roundMoney($quantity * $unitPrice);
            $discountAmount = $this->roundMoney($lineGross * ($discountRate / 100));
            $lineSubtotal = $this->roundMoney($lineGross - $discountAmount);
            $lineTax = $this->roundMoney($lineSubtotal * ($taxRate / 100));
            $lineTotal = $this->roundMoney($lineSubtotal + $lineTax);

            $reservationLocationId = $this->resolveReservationLocation(
                $line['reservation_location_id'] ?? null,
                $branch->id,
                $reserveStock
            );

            $salesOrder->lines()->create([
                'line_number' => $lineNumber++,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'description' => $line['description'] ?? $product->name,
                'uom_id' => $orderedUom->id,
                'base_uom_id' => $baseUom->id,
                'quantity' => $quantity,
                'quantity_base' => $quantityBase,
                'unit_price' => $unitPrice,
                'discount_rate' => $discountRate,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
                'requested_delivery_date' => isset($line['requested_delivery_date'])
                    ? Carbon::parse($line['requested_delivery_date'])
                    : null,
                'reservation_location_id' => $reservationLocationId,
                'start_date' => isset($line['start_date']) ? Carbon::parse($line['start_date']) : null,
                'end_date' => isset($line['end_date']) ? Carbon::parse($line['end_date']) : null,
                'resource_pool_id' => $line['resource_pool_id'] ?? ($product->resourcePools->first()?->id),
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

    /**
     * Persist costs for a sales order.
     */
    private function persistCosts(SalesOrder $salesOrder, array $costs): void
    {
        // Delete existing costs for update case
        $salesOrder->costs()->delete();

        foreach ($costs as $cost) {
            if (!isset($cost['amount']) || (float) $cost['amount'] <= 0) {
                continue;
            }

            SalesOrderCost::create([
                'sales_order_id' => $salesOrder->id,
                'cost_item_id' => $cost['cost_item_id'] ?? null,
                'description' => $cost['description'] ?? null,
                'amount' => $cost['amount'],
                'currency_id' => $cost['currency_id'] ?? $salesOrder->currency_id,
                'exchange_rate' => $cost['exchange_rate'] ?? $salesOrder->exchange_rate ?? 1,
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $linesPayload
     * @return array<int, array<string, mixed>>
     */
    private function prepareDeliveryLines(SalesOrder $salesOrder, array $linesPayload): array
    {
        $payloadMap = collect($linesPayload)
            ->filter(fn ($line) => isset($line['sales_order_line_id']))
            ->mapWithKeys(fn ($line) => [
                (int) $line['sales_order_line_id'] => (float) ($line['quantity'] ?? 0),
            ]);

        $prepared = [];

        foreach ($salesOrder->lines as $line) {
            if (!$payloadMap->has($line->id)) {
                continue;
            }

            $quantity = $this->roundQuantity(max(0, $payloadMap->get($line->id, 0)));
            if ($quantity <= 0) {
                continue;
            }

            $remaining = (float) $line->quantity - (float) $line->quantity_delivered;
            if ($quantity - $remaining > self::QTY_TOLERANCE) {
                throw new SalesOrderException(
                    sprintf('Jumlah pengiriman melebihi sisa untuk SKU %s.', $line->variant?->sku ?? $line->id)
                );
            }

            try {
                $quantityBase = $this->roundQuantity(
                    $this->uomConverter->convert($quantity, $line->uom_id, $line->base_uom_id)
                );
            } catch (RuntimeException $exception) {
                throw new SalesOrderException($exception->getMessage(), previous: $exception);
            }

            $prepared[] = [
                'order_line' => $line,
                'quantity' => $quantity,
                'quantity_base' => $quantityBase,
                'unit_price' => (float) $line->unit_price,
                'line_total' => $this->roundMoney($quantity * (float) $line->unit_price),
            ];
        }

        if (empty($prepared)) {
            throw new SalesOrderException('Tidak ada baris pengiriman yang valid.');
        }

        return $prepared;
    }

    private function consumeReservation(SalesOrderLine $line, float $quantityBase): void
    {
        if ($quantityBase <= 0 || !$line->reservation_location_id) {
            return;
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
                max(0, (float) $item->qty_reserved - $quantityBase)
            );
            $item->save();
        }
    }

    private function syncSalesOrderDeliveryStatus(SalesOrder $salesOrder, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();
        $currentStatus = SalesOrderStatus::from($salesOrder->status);

        $hasRemaining = $salesOrder->lines->contains(function ($line) {
            return ((float) $line->quantity_base - (float) $line->quantity_delivered_base) > self::QTY_TOLERANCE;
        });

        if ($hasRemaining) {
            if ($currentStatus === SalesOrderStatus::CONFIRMED) {
                $salesOrder->transitionTo(SalesOrderStatus::PARTIALLY_DELIVERED, $actor);
            }

            return;
        }

        if (!in_array($currentStatus, [SalesOrderStatus::DELIVERED, SalesOrderStatus::CLOSED], true)) {
            $salesOrder->transitionTo(SalesOrderStatus::DELIVERED, $actor);
        }
    }

    private function generateDeliveryNumber(int $companyId, int $branchId, Carbon $deliveryDate): string
    {
        $config = config('sales.delivery_numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'SD');
        $sequencePadding = (int) ($config['sequence_padding'] ?? 5);
        $sequence = str_pad(
            (string) $this->nextDeliverySequence($branchId, $deliveryDate),
            $sequencePadding,
            '0',
            STR_PAD_LEFT
        );

        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $deliveryDate->format('y');

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function nextDeliverySequence(int $branchId, Carbon $deliveryDate): int
    {
        $latest = SalesDelivery::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('delivery_date', $deliveryDate->year)
            ->orderByDesc('delivery_number')
            ->value('delivery_number');

        if (!$latest) {
            return 1;
        }

        $segments = explode('.', $latest);
        $last = (int) (end($segments) ?: 0);

        return $last + 1;
    }

    private function dispatchDeliveryEvent(
        SalesDelivery $delivery,
        SalesOrder $salesOrder,
        float $totalCogs,
        ?Authenticatable $actor = null
    ): void {
        if ($totalCogs <= 0) {
            return;
        }

        $payload = AccountingEventBuilder::forDocument(AccountingEventCode::SALES_DELIVERY_POSTED, [
            'company_id' => $salesOrder->company_id,
            'branch_id' => $salesOrder->branch_id,
            'document_type' => 'sales_delivery',
            'document_id' => $delivery->id,
            'document_number' => $delivery->delivery_number,
            'currency_code' => $salesOrder->currency?->code ?? 'IDR',
            'exchange_rate' => (float) $salesOrder->exchange_rate,
            'occurred_at' => CarbonImmutable::parse($delivery->delivery_date ?? now()),
            'actor_id' => $actor?->getAuthIdentifier(),
            'meta' => [
                'sales_order_id' => $salesOrder->id,
                'inventory_transaction_id' => $delivery->inventory_transaction_id,
            ],
        ])->debit('cogs', $this->roundCost($totalCogs))
            ->credit('inventory', $this->roundCost($totalCogs))
            ->build();

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function ($throwable) {
            report($throwable);
        });
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

    private function resolveUnitPriceForProduct(
        Product $product,
        ?ProductVariant $variant,
        int $uomId,
        float $quantity,
        array $context,
        ?float $explicitPrice
    ): float {
        if ($explicitPrice !== null) {
            return $this->roundCost($explicitPrice);
        }

        $quote = $this->pricingService->quote(
            $product->id,
            $variant?->id,
            $uomId,
            $quantity,
            $context
        );

        return $this->roundCost((float) ($quote['price'] ?? 0));
    }

    private function resolveTaxRateForProduct(
        Product $product,
        array $context,
        ?float $explicitRate
    ): float {
        if ($explicitRate !== null) {
            return round($explicitRate, 2);
        }

        $quote = $this->taxService->quote(
            $product,
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


