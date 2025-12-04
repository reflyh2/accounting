<?php

namespace App\Services\Sales;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\Documents\SalesDeliveryStatus;
use App\Enums\Documents\SalesOrderStatus;
use App\Enums\Documents\SalesReturnStatus;
use App\Exceptions\SalesReturnException;
use App\Models\SalesDelivery;
use App\Models\SalesDeliveryLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesReturn;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Inventory\DTO\ReceiptDTO;
use App\Services\Inventory\DTO\ReceiptLineDTO;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalesReturnService
{
    private const QTY_TOLERANCE = 0.0005;
    private const COST_SCALE = 6;

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AccountingEventBus $accountingEventBus,
    ) {
    }

    public function create(array $payload, ?Authenticatable $actor = null): SalesReturn
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            /** @var SalesDelivery $salesDelivery */
            $salesDelivery = SalesDelivery::with([
                'salesOrder.branch.branchGroup.company',
                'salesOrder.partner',
                'salesOrder.currency',
                'currency',
                'location',
                'lines.salesOrderLine',
            ])->findOrFail($payload['sales_delivery_id']);

            $this->assertReturnable($salesDelivery);

            $preparedLines = $this->prepareLines($salesDelivery, $payload['lines'] ?? []);

            if (empty($preparedLines)) {
                throw new SalesReturnException('Minimal satu baris retur wajib diisi.');
            }

            $returnDate = Carbon::parse($payload['return_date']);
            $salesOrder = $salesDelivery->salesOrder;

            $salesReturn = SalesReturn::create([
                'sales_order_id' => $salesOrder->id,
                'sales_delivery_id' => $salesDelivery->id,
                'company_id' => $salesDelivery->company_id,
                'branch_id' => $salesDelivery->branch_id,
                'partner_id' => $salesOrder->partner_id,
                'location_id' => $salesDelivery->location_id,
                'currency_id' => $salesDelivery->currency_id,
                'return_number' => $this->generateReturnNumber(
                    $salesDelivery->company_id,
                    $salesDelivery->branch_id,
                    $returnDate
                ),
                'status' => SalesReturnStatus::DRAFT->value,
                'return_date' => $returnDate,
                'reason_code' => $payload['reason_code'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'exchange_rate' => $salesOrder->exchange_rate ?? 1,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $salesReturn->lines()->createMany($preparedLines);

            $deliveryLineIds = collect($preparedLines)->pluck('sales_delivery_line_id')->unique();
            $soLineIds = collect($preparedLines)->pluck('sales_order_line_id')->unique();

            /** @var \Illuminate\Support\Collection<int, SalesDeliveryLine> $lockedDeliveryLines */
            $lockedDeliveryLines = SalesDeliveryLine::whereIn('id', $deliveryLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var \Illuminate\Support\Collection<int, SalesOrderLine> $lockedSoLines */
            $lockedSoLines = SalesOrderLine::whereIn('id', $soLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $receiptLines = [];

            foreach ($preparedLines as $line) {
                /** @var SalesDeliveryLine|null $deliveryLine */
                $deliveryLine = $lockedDeliveryLines->get($line['sales_delivery_line_id']);
                /** @var SalesOrderLine|null $soLine */
                $soLine = $lockedSoLines->get($line['sales_order_line_id']);

                if (!$deliveryLine || !$soLine) {
                    throw new SalesReturnException('Baris retur tidak valid.');
                }

                $deliveryLine->quantity_returned = $this->roundQuantity(
                    (float) $deliveryLine->quantity_returned + $line['quantity']
                );
                $deliveryLine->amount_returned = $this->roundMoney(
                    (float) $deliveryLine->amount_returned + $line['line_total']
                );
                $deliveryLine->save();

                $soLine->quantity_delivered = $this->roundQuantity(
                    max(0, (float) $soLine->quantity_delivered - $line['quantity'])
                );
                $soLine->quantity_returned = $this->roundQuantity(
                    (float) $soLine->quantity_returned + $line['quantity']
                );
                $soLine->save();

                $receiptLines[] = new ReceiptLineDTO(
                    $deliveryLine->product_variant_id,
                    $deliveryLine->base_uom_id,
                    $line['quantity_base'],
                    (float) $deliveryLine->unit_cost_base
                );
            }

            $receiptResult = $this->inventoryService->receipt(new ReceiptDTO(
                $returnDate,
                $salesDelivery->location_id,
                $receiptLines,
                sourceType: SalesReturn::class,
                sourceId: $salesReturn->id,
                notes: $payload['notes'] ?? null,
                valuationMethod: $salesDelivery->valuation_method
            ));

            $totalQuantityBase = collect($preparedLines)->sum('quantity_base');
            $totalValue = collect($preparedLines)->sum('line_total');
            $totalValueBase = collect($preparedLines)->sum('line_total_base');

            $salesReturn->transitionTo(SalesReturnStatus::POSTED, $actor);
            $salesReturn->update([
                'inventory_transaction_id' => $receiptResult->transaction->id,
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_value' => $this->roundMoney($totalValue),
                'total_value_base' => $this->roundCost($totalValueBase),
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $salesOrder->load('lines');
            $this->syncSalesOrderDeliveryStatus($salesOrder, $actor);

            $this->dispatchReturnEvent($salesReturn, $salesOrder, $totalValueBase, $actor);

            return $salesReturn->fresh([
                'salesOrder.partner',
                'salesDelivery',
                'lines.variant.product',
            ]);
        });
    }

    private function assertReturnable(SalesDelivery $salesDelivery): void
    {
        if ($salesDelivery->status !== SalesDeliveryStatus::POSTED->value) {
            throw new SalesReturnException('Retur hanya dapat dilakukan untuk delivery yang sudah diposting.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prepareLines(SalesDelivery $salesDelivery, array $linesPayload): array
    {
        $lines = $salesDelivery->lines->keyBy('id');
        $prepared = [];

        foreach ($linesPayload as $payloadLine) {
            $lineId = (int) ($payloadLine['sales_delivery_line_id'] ?? 0);
            $quantity = (float) ($payloadLine['quantity'] ?? 0);

            if ($lineId === 0) {
                continue;
            }

            if ($quantity <= 0) {
                throw new SalesReturnException('Jumlah retur harus lebih dari nol.');
            }

            /** @var SalesDeliveryLine|null $line */
            $line = $lines->get($lineId);

            if (!$line) {
                throw new SalesReturnException('Baris delivery tidak ditemukan.');
            }

            $available = $this->availableQuantity($line);

            if (($quantity - $available) > self::QTY_TOLERANCE) {
                throw new SalesReturnException('Jumlah retur melebihi saldo yang tersedia.');
            }

            $quantityBase = $this->deriveBaseQuantity($quantity, $line);
            $unitPrice = (float) $line->unit_price;
            $unitCostBase = (float) $line->unit_cost_base;

            $prepared[] = [
                'sales_delivery_line_id' => $line->id,
                'sales_order_line_id' => $line->sales_order_line_id,
                'product_id' => $line->product_id,
                'product_variant_id' => $line->product_variant_id,
                'description' => $line->description,
                'uom_id' => $line->uom_id,
                'base_uom_id' => $line->base_uom_id,
                'quantity' => $this->roundQuantity($quantity),
                'quantity_base' => $this->roundQuantity($quantityBase),
                'unit_price' => $unitPrice,
                'unit_cost_base' => $unitCostBase,
                'line_total' => $this->roundMoney($quantity * $unitPrice),
                'line_total_base' => $this->roundCost($quantityBase * $unitCostBase),
            ];
        }

        return $prepared;
    }

    private function availableQuantity(SalesDeliveryLine $line): float
    {
        $available = (float) $line->quantity
            - (float) $line->quantity_invoiced
            - (float) $line->quantity_returned;

        return max(0, $available);
    }

    private function deriveBaseQuantity(float $quantity, SalesDeliveryLine $line): float
    {
        if ((float) $line->quantity <= 0) {
            return $quantity;
        }

        $ratio = (float) $line->quantity_base / (float) $line->quantity;

        return $quantity * $ratio;
    }

    /**
     * Attempt to restore original cost layers from the delivery
     * @return array<int, array<string, mixed>>|null
     */
    private function restoreCostLayers(array $preparedLines, SalesDelivery $salesDelivery): ?array
    {
        // For now, return null to use standard costing
        // In a full implementation, this would restore the original cost layers
        // from the delivery's cost consumption
        return null;
    }

    private function generateReturnNumber(int $companyId, int $branchId, Carbon $returnDate): string
    {
        $config = config('sales.sales_return_numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'SRN');
        $sequencePadding = (int) ($config['sequence_padding'] ?? 5);
        $sequence = str_pad(
            (string) $this->nextReturnSequence($branchId, $returnDate),
            $sequencePadding,
            '0',
            STR_PAD_LEFT
        );

        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $returnDate->format('y');

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function nextReturnSequence(int $branchId, Carbon $returnDate): int
    {
        $latest = SalesReturn::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('return_date', $returnDate->year)
            ->orderByDesc('return_number')
            ->value('return_number');

        if (!$latest) {
            return 1;
        }

        $segments = explode('.', $latest);
        $last = (int) (end($segments) ?: 0);

        return $last + 1;
    }

    private function syncSalesOrderDeliveryStatus(SalesOrder $salesOrder, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        $hasRemaining = $salesOrder->lines->contains(function ($line) {
            $remaining = ((float) $line->quantity - (float) $line->quantity_delivered);
            return $remaining > self::QTY_TOLERANCE;
        });

        $target = $hasRemaining
            ? SalesOrderStatus::PARTIALLY_DELIVERED
            : SalesOrderStatus::DELIVERED;

        $salesOrder->transitionTo(
            $target,
            $actor,
            ['enforceMakerChecker' => (bool) config('sales.maker_checker.enforce', false)]
        );
    }

    private function dispatchReturnEvent(
        SalesReturn $salesReturn,
        SalesOrder $salesOrder,
        float $amountBase,
        ?Authenticatable $actor = null
    ): void {
        if ($amountBase <= 0) {
            return;
        }

        $payload = new AccountingEventPayload(
            AccountingEventCode::SALES_RETURN_POSTED,
            $salesReturn->company_id,
            $salesReturn->branch_id,
            'sales_return',
            $salesReturn->id,
            $salesReturn->return_number,
            $salesOrder->currency?->code ?? 'IDR',
            (float) $salesOrder->exchange_rate,
            CarbonImmutable::parse($salesReturn->return_date),
            $actor?->getAuthIdentifier(),
            [
                'sales_order_id' => $salesOrder->id,
                'sales_order_number' => $salesOrder->order_number,
                'sales_delivery_id' => $salesReturn->sales_delivery_id,
                'inventory_transaction_id' => $salesReturn->inventory_transaction_id,
            ]
        );

        $normalizedAmount = $this->roundCost($amountBase);

        $payload->setLines([
            AccountingEntry::debit('inventory', $normalizedAmount),
            AccountingEntry::credit('sales_returns', $normalizedAmount),
        ]);

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function (Throwable $throwable) {
            report($throwable);
        });
    }

    private function roundQuantity(float $value): float
    {
        return round($value, 3);
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private function roundCost(float $value): float
    {
        return round($value, self::COST_SCALE);
    }
}
