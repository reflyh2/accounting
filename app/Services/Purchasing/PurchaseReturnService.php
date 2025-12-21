<?php

namespace App\Services\Purchasing;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Enums\Documents\GoodsReceiptStatus;
use App\Enums\Documents\PurchaseOrderStatus;
use App\Enums\Documents\PurchaseReturnStatus;
use App\Exceptions\PurchaseReturnException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReturn;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Inventory\DTO\IssueDTO;
use App\Services\Inventory\DTO\IssueLineDTO;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurchaseReturnService
{
    private const QTY_TOLERANCE = 0.0005;
    private const COST_SCALE = 6;

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AccountingEventBus $accountingEventBus,
    ) {
    }

    public function create(array $payload, ?Authenticatable $actor = null): PurchaseReturn
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            /** @var GoodsReceipt $goodsReceipt */
            $goodsReceipt = GoodsReceipt::with([
                'purchaseOrders.branch.branchGroup.company',
                'purchaseOrders.partner',
                'purchaseOrders.currency',
                'supplier',
                'currency',
                'location',
                'lines.purchaseOrderLine',
            ])->findOrFail($payload['goods_receipt_id']);

            $this->assertReturnable($goodsReceipt);

            $preparedLines = $this->prepareLines($goodsReceipt, $payload['lines'] ?? []);

            if (empty($preparedLines)) {
                throw new PurchaseReturnException('Minimal satu baris retur wajib diisi.');
            }

            $returnDate = Carbon::parse($payload['return_date']);
            
            // Get the first purchase order for exchange rate (they should all have the same currency/rate)
            $firstPurchaseOrder = $goodsReceipt->purchaseOrders->first();

            $purchaseReturn = PurchaseReturn::create([
                'purchase_order_id' => $firstPurchaseOrder?->id,
                'goods_receipt_id' => $goodsReceipt->id,
                'company_id' => $goodsReceipt->company_id,
                'branch_id' => $goodsReceipt->branch_id,
                'partner_id' => $goodsReceipt->supplier_id,
                'location_id' => $goodsReceipt->location_id,
                'currency_id' => $goodsReceipt->currency_id,
                'return_number' => $this->generateReturnNumber(
                    $goodsReceipt->company_id,
                    $goodsReceipt->branch_id,
                    $returnDate
                ),
                'status' => PurchaseReturnStatus::DRAFT->value,
                'return_date' => $returnDate,
                'reason_code' => $payload['reason_code'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'exchange_rate' => $firstPurchaseOrder?->exchange_rate ?? 1,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $purchaseReturn->lines()->createMany($preparedLines);

            $grnLineIds = collect($preparedLines)->pluck('goods_receipt_line_id')->unique();
            $poLineIds = collect($preparedLines)->pluck('purchase_order_line_id')->unique();

            /** @var \Illuminate\Support\Collection<int, GoodsReceiptLine> $lockedGrnLines */
            $lockedGrnLines = GoodsReceiptLine::whereIn('id', $grnLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var \Illuminate\Support\Collection<int, PurchaseOrderLine> $lockedPoLines */
            $lockedPoLines = PurchaseOrderLine::whereIn('id', $poLineIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $issueLines = [];

            foreach ($preparedLines as $line) {
                /** @var GoodsReceiptLine|null $grnLine */
                $grnLine = $lockedGrnLines->get($line['goods_receipt_line_id']);
                /** @var PurchaseOrderLine|null $poLine */
                $poLine = $lockedPoLines->get($line['purchase_order_line_id']);

                if (!$grnLine || !$poLine) {
                    throw new PurchaseReturnException('Baris retur tidak valid.');
                }

                $grnLine->quantity_returned = $this->roundQuantity(
                    (float) $grnLine->quantity_returned + $line['quantity']
                );
                $grnLine->quantity_returned_base = $this->roundQuantity(
                    (float) $grnLine->quantity_returned_base + $line['quantity_base']
                );
                $grnLine->amount_returned = $this->roundMoney(
                    (float) $grnLine->amount_returned + $line['line_total']
                );
                $grnLine->save();

                $poLine->quantity_received = $this->roundQuantity(
                    max(0, (float) $poLine->quantity_received - $line['quantity'])
                );
                $poLine->quantity_received_base = $this->roundQuantity(
                    max(0, (float) $poLine->quantity_received_base - $line['quantity_base'])
                );
                $poLine->quantity_returned = $this->roundQuantity(
                    (float) $poLine->quantity_returned + $line['quantity']
                );
                $poLine->quantity_returned_base = $this->roundQuantity(
                    (float) $poLine->quantity_returned_base + $line['quantity_base']
                );
                $poLine->save();

                $issueLines[] = new IssueLineDTO(
                    $grnLine->product_variant_id,
                    $grnLine->base_uom_id,
                    $line['quantity_base']
                );
            }

            $issueResult = $this->inventoryService->issue(new IssueDTO(
                $returnDate,
                $goodsReceipt->location_id,
                $issueLines,
                sourceType: PurchaseReturn::class,
                sourceId: $purchaseReturn->id,
                notes: $payload['notes'] ?? null,
                valuationMethod: $goodsReceipt->valuation_method
            ));

            $totalQuantityBase = collect($preparedLines)->sum('quantity_base');
            $totalValue = collect($preparedLines)->sum('line_total');
            $totalValueBase = collect($preparedLines)->sum('line_total_base');

            $purchaseReturn->transitionTo(PurchaseReturnStatus::POSTED, $actor);
            $purchaseReturn->update([
                'inventory_transaction_id' => $issueResult->transaction->id,
                'total_quantity' => $this->roundQuantity($totalQuantityBase),
                'total_value' => $this->roundMoney($totalValue),
                'total_value_base' => $this->roundCost($totalValueBase),
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            // Sync receipt status for all related purchase orders
            foreach ($goodsReceipt->purchaseOrders as $purchaseOrder) {
                $purchaseOrder->load('lines');
                $this->syncPurchaseOrderReceiptStatus($purchaseOrder, $actor);
            }

            $this->dispatchReturnEvent($purchaseReturn, $goodsReceipt, $totalValueBase, $actor);

            return $purchaseReturn->fresh([
                'purchaseOrder.partner',
                'goodsReceipt',
                'lines.variant.product',
            ]);
        });
    }

    private function assertReturnable(GoodsReceipt $goodsReceipt): void
    {
        if ($goodsReceipt->status !== GoodsReceiptStatus::POSTED->value) {
            throw new PurchaseReturnException('Retur hanya dapat dilakukan untuk GRN yang sudah diposting.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prepareLines(GoodsReceipt $goodsReceipt, array $linesPayload): array
    {
        $lines = $goodsReceipt->lines->keyBy('id');
        $prepared = [];

        foreach ($linesPayload as $payloadLine) {
            $lineId = (int) ($payloadLine['goods_receipt_line_id'] ?? 0);
            $quantity = (float) ($payloadLine['quantity'] ?? 0);

            if ($lineId === 0) {
                continue;
            }

            if ($quantity <= 0) {
                throw new PurchaseReturnException('Jumlah retur harus lebih dari nol.');
            }

            /** @var GoodsReceiptLine|null $line */
            $line = $lines->get($lineId);

            if (!$line) {
                throw new PurchaseReturnException('Baris penerimaan tidak ditemukan.');
            }

            $available = $this->availableQuantity($line);

            if (($quantity - $available) > self::QTY_TOLERANCE) {
                throw new PurchaseReturnException('Jumlah retur melebihi saldo yang tersedia.');
            }

            $quantityBase = $this->deriveBaseQuantity($quantity, $line);
            $unitPrice = (float) $line->unit_price;
            $unitCostBase = (float) $line->unit_cost_base;

            $prepared[] = [
                'goods_receipt_line_id' => $line->id,
                'purchase_order_line_id' => $line->purchase_order_line_id,
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

    private function availableQuantity(GoodsReceiptLine $line): float
    {
        $available = (float) $line->quantity
            - (float) $line->quantity_invoiced
            - (float) $line->quantity_returned;

        return max(0, $available);
    }

    private function deriveBaseQuantity(float $quantity, GoodsReceiptLine $line): float
    {
        if ((float) $line->quantity <= 0) {
            return $quantity;
        }

        $ratio = (float) $line->quantity_base / (float) $line->quantity;

        return $quantity * $ratio;
    }

    private function generateReturnNumber(int $companyId, int $branchId, Carbon $returnDate): string
    {
        $config = config('purchasing.purchase_return_numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'PRN');
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
        $latest = PurchaseReturn::withTrashed()
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

    private function syncPurchaseOrderReceiptStatus(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        $hasRemaining = $purchaseOrder->lines->contains(function ($line) {
            $remaining = ((float) $line->quantity - (float) $line->quantity_received);
            return $remaining > self::QTY_TOLERANCE;
        });

        $target = $hasRemaining
            ? PurchaseOrderStatus::PARTIALLY_RECEIVED
            : PurchaseOrderStatus::RECEIVED;

        $purchaseOrder->transitionTo(
            $target,
            $actor,
            ['enforceMakerChecker' => (bool) config('purchasing.maker_checker.enforce', false)]
        );
    }

    private function dispatchReturnEvent(
        PurchaseReturn $purchaseReturn,
        GoodsReceipt $goodsReceipt,
        float $amountBase,
        ?Authenticatable $actor = null
    ): void {
        if ($amountBase <= 0) {
            return;
        }

        $firstPurchaseOrder = $goodsReceipt->purchaseOrders->first();

        $payload = new AccountingEventPayload(
            AccountingEventCode::PURCHASE_RETURN_POSTED,
            $purchaseReturn->company_id,
            $purchaseReturn->branch_id,
            'purchase_return',
            $purchaseReturn->id,
            $purchaseReturn->return_number,
            $goodsReceipt->currency?->code ?? 'IDR',
            (float) ($firstPurchaseOrder?->exchange_rate ?? 1),
            CarbonImmutable::parse($purchaseReturn->return_date),
            $actor?->getAuthIdentifier(),
            [
                'purchase_order_id' => $firstPurchaseOrder?->id,
                'purchase_order_number' => $firstPurchaseOrder?->order_number,
                'goods_receipt_id' => $purchaseReturn->goods_receipt_id,
                'inventory_transaction_id' => $purchaseReturn->inventory_transaction_id,
            ]
        );

        $normalizedAmount = $this->roundCost($amountBase);

        $payload->setLines([
            AccountingEntry::debit('goods_received_not_invoiced', $normalizedAmount),
            AccountingEntry::credit('inventory', $normalizedAmount),
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
