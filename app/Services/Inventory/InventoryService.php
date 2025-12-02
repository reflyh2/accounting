<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryException;
use App\Models\CostLayer;
use App\Models\InventoryCostConsumption;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionLine;
use App\Models\ProductVariant;
use App\Models\Location;
use App\Services\Inventory\DTO\AdjustDTO;
use App\Services\Inventory\DTO\AdjustLineDTO;
use App\Services\Inventory\DTO\InventoryTxnResult;
use App\Services\Inventory\DTO\IssueDTO;
use App\Services\Inventory\DTO\IssueLineDTO;
use App\Services\Inventory\DTO\ReceiptDTO;
use App\Services\Inventory\DTO\ReceiptLineDTO;
use App\Services\Inventory\DTO\TransferDTO;
use App\Services\Inventory\DTO\TransferLineDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    private const QTY_SCALE = 3;
    private const COST_SCALE = 4;
    private const TOLERANCE = 0.0005;
    private const EFFECT_IN = 'in';
    private const EFFECT_OUT = 'out';

    public function receipt(ReceiptDTO $dto, ?InventoryTransaction $transaction = null): InventoryTxnResult
    {
        $valuation = $this->normalizeValuation($dto->valuationMethod, $dto->locationId);

        return DB::transaction(function () use ($dto, $valuation, $transaction) {
            $transaction = $this->prepareTransaction('receipt', [
                'transaction_date' => $dto->transactionDate,
                'source_type' => $dto->sourceType,
                'source_id' => $dto->sourceId,
                'location_id_to' => $dto->locationId,
                'notes' => $dto->notes,
            ], $transaction);

            $summary = $this->handleReceiptLinesBatch($transaction, $dto->locationId, $dto->lines, $valuation);

            return new InventoryTxnResult(
                $transaction->load('lines.productVariant'),
                $summary['qty'],
                $summary['value'],
            );
        });
    }

    public function issue(IssueDTO $dto, ?InventoryTransaction $transaction = null): InventoryTxnResult
    {
        $valuation = $this->normalizeValuation($dto->valuationMethod, $dto->locationId);

        return DB::transaction(function () use ($dto, $valuation, $transaction) {
            $transaction = $this->prepareTransaction('issue', [
                'transaction_date' => $dto->transactionDate,
                'source_type' => $dto->sourceType,
                'source_id' => $dto->sourceId,
                'location_id_from' => $dto->locationId,
                'notes' => $dto->notes,
            ], $transaction);

            $result = $this->processIssueLines($transaction, $dto->lines, $dto->locationId, $valuation);

            return new InventoryTxnResult(
                $transaction->load('lines.productVariant'),
                $result['qty'],
                $result['value'],
            );
        });
    }

    public function adjust(AdjustDTO $dto, ?InventoryTransaction $transaction = null): InventoryTxnResult
    {
        $valuation = $this->normalizeValuation($dto->valuationMethod, $dto->locationId);

        return DB::transaction(function () use ($dto, $valuation, $transaction) {
            $transaction = $this->prepareTransaction('adjustment', [
                'transaction_date' => $dto->transactionDate,
                'source_type' => $dto->reason,
                'location_id_from' => $dto->locationId,
                'location_id_to' => $dto->locationId,
                'notes' => $dto->notes,
            ], $transaction);

            $netQty = 0.0;
            $netValue = 0.0;

            foreach ($dto->lines as $lineDto) {
                $qty = $this->roundQty($lineDto->quantity);
                if ($qty > 0) {
                    $netQty += $this->handleReceiptLine($transaction, $dto->locationId, new ReceiptLineDTO(
                        $lineDto->productVariantId,
                        $lineDto->uomId,
                        $qty,
                        $lineDto->unitCost ?? throw new InventoryException('Unit cost is required for positive adjustments.'),
                        $lineDto->lotId,
                        $lineDto->serialId,
                    ), $valuation, $netValue);
                } elseif ($qty < 0) {
                    $result = $this->issueLine(
                        $transaction,
                        $dto->locationId,
                        new IssueLineDTO(
                            $lineDto->productVariantId,
                            $lineDto->uomId,
                            abs($qty),
                            $lineDto->lotId,
                            $lineDto->serialId
                        ),
                        $valuation
                    );
                    $netQty -= abs($qty);
                    $netValue -= $result['value'];
                }
            }

            return new InventoryTxnResult(
                $transaction->load('lines.productVariant'),
                $this->roundQty($netQty),
                $this->roundCost($netValue),
            );
        });
    }

    public function transfer(TransferDTO $dto, ?InventoryTransaction $transaction = null): InventoryTxnResult
    {
        if ($dto->locationIdFrom === $dto->locationIdTo) {
            throw new InventoryException('Lokasi asal dan tujuan tidak boleh sama.');
        }

        $valuation = $this->normalizeValuation($dto->valuationMethod, $dto->locationIdFrom);

        return DB::transaction(function () use ($dto, $valuation, $transaction) {
            $transaction = $this->prepareTransaction('transfer', [
                'transaction_date' => $dto->transactionDate,
                'location_id_from' => $dto->locationIdFrom,
                'location_id_to' => $dto->locationIdTo,
                'notes' => $dto->notes,
            ], $transaction);

            $totalQty = 0.0;
            $totalValue = 0.0;

            foreach ($dto->lines as $lineDto) {
                $issueResult = $this->issueLine(
                    $transaction,
                    $dto->locationIdFrom,
                    new IssueLineDTO(
                        $lineDto->productVariantId,
                        $lineDto->uomId,
                        $lineDto->quantity,
                        $lineDto->lotId,
                        $lineDto->serialId
                    ),
                    $valuation
                );

                foreach ($issueResult['segments'] as $segment) {
                    if ($segment['qty'] <= 0) {
                        continue;
                    }

                    $this->applyInventoryDelta(
                        $issueResult['variant'],
                        $dto->locationIdTo,
                        $segment['lot_id'],
                        $segment['serial_id'],
                        $segment['qty']
                    );

                    $inboundLine = $this->createLine(
                        $transaction,
                        $issueResult['variant']->id,
                        $lineDto->uomId,
                        $segment['qty'],
                        $segment['unit_cost'],
                        $segment['lot_id'],
                        $segment['serial_id'],
                        self::EFFECT_IN
                    );

                    $this->createCostLayerFromReceipt(
                        $inboundLine,
                        $dto->locationIdTo,
                        $segment['qty'],
                        $segment['unit_cost'],
                        $valuation,
                        $segment['lot_id'],
                        $segment['serial_id']
                    );
                }

                $totalQty += $lineDto->quantity;
                $totalValue += $issueResult['value'];
            }

            return new InventoryTxnResult(
                $transaction->load('lines.productVariant'),
                $this->roundQty($totalQty),
                $this->roundCost($totalValue),
            );
        });
    }

    public function deleteTransaction(InventoryTransaction $transaction): void
    {
        DB::transaction(fn () => $this->deleteTransactionInternal($transaction, false));
    }

    private function handleReceiptLinesBatch(
        InventoryTransaction $transaction,
        int $locationId,
        array $lines,
        string $valuationMethod
    ): array {
        $totalQty = 0.0;
        $totalValue = 0.0;

        foreach ($lines as $lineDto) {
            $totalQty += $this->handleReceiptLine($transaction, $locationId, $lineDto, $valuationMethod, $totalValue);
        }

        return [
            'qty' => $this->roundQty($totalQty),
            'value' => $this->roundCost($totalValue),
        ];
    }

    private function handleReceiptLine(
        InventoryTransaction $transaction,
        int $locationId,
        ReceiptLineDTO $lineDto,
        string $valuationMethod,
        float &$totalValue
    ): float {
        $variant = ProductVariant::findOrFail($lineDto->productVariantId);
        $quantity = $this->roundQty($lineDto->quantity);
        if ($quantity <= 0) {
            throw new InventoryException('Quantity must be greater than zero.');
        }

        $unitCost = $this->roundCost($lineDto->unitCost);
        if ($unitCost < 0) {
            throw new InventoryException('Unit cost must be zero or greater.');
        }

        $line = $this->createLine(
            $transaction,
            $variant->id,
            $lineDto->uomId,
            $quantity,
            $unitCost,
            $lineDto->lotId,
            $lineDto->serialId,
            self::EFFECT_IN
        );

        $this->applyInventoryDelta($variant, $locationId, $lineDto->lotId, $lineDto->serialId, $quantity);
        $this->createCostLayerFromReceipt($line, $locationId, $quantity, $unitCost, $valuationMethod, $lineDto->lotId, $lineDto->serialId);

        $lineValue = $quantity * $unitCost;
        $totalValue += $lineValue;

        return $quantity;
    }

    private function processIssueLines(
        InventoryTransaction $transaction,
        array $lineDtos,
        int $locationId,
        string $valuationMethod
    ): array {
        $totalQty = 0.0;
        $totalValue = 0.0;

        foreach ($lineDtos as $lineDto) {
            $result = $this->issueLine($transaction, $locationId, $lineDto, $valuationMethod);
            $totalQty += $lineDto->quantity;
            $totalValue += $result['value'];
        }

        return [
            'qty' => $this->roundQty($totalQty),
            'value' => $this->roundCost($totalValue),
        ];
    }

    /**
     * @return array{line: InventoryTransactionLine, unit_cost: float, value: float, segments: array<int, array>, variant: ProductVariant}
     */
    private function issueLine(
        InventoryTransaction $transaction,
        int $locationId,
        IssueLineDTO $lineDto,
        string $valuationMethod
    ): array {
        $variant = ProductVariant::findOrFail($lineDto->productVariantId);
        $quantity = $this->roundQty($lineDto->quantity);
        if ($quantity <= 0) {
            throw new InventoryException('Quantity must be greater than zero.');
        }

        $consumption = $this->consumeCostLayers(
            $variant->id,
            $locationId,
            $quantity,
            $valuationMethod,
            $lineDto->lotId,
            $lineDto->serialId
        );

        $this->applyInventoryDelta($variant, $locationId, $lineDto->lotId, $lineDto->serialId, -$quantity);

        $line = $this->createLine(
            $transaction,
            $variant->id,
            $lineDto->uomId,
            $quantity,
            $consumption['unit_cost'],
            $lineDto->lotId,
            $lineDto->serialId,
            self::EFFECT_OUT
        );

        $this->recordCostConsumptions($line, $consumption['segments']);

        return [
            'line' => $line,
            'unit_cost' => $consumption['unit_cost'],
            'value' => $consumption['total_value'],
            'segments' => $consumption['segments'],
            'variant' => $variant,
        ];
    }

    private function recordCostConsumptions(InventoryTransactionLine $line, array $segments): void
    {
        foreach ($segments as $segment) {
            if (!isset($segment['cost_layer_id'])) {
                continue;
            }

            InventoryCostConsumption::create([
                'inventory_transaction_line_id' => $line->id,
                'cost_layer_id' => $segment['cost_layer_id'],
                'quantity' => $segment['qty'],
                'unit_cost' => $segment['unit_cost'],
            ]);
        }
    }

    private function consumeCostLayers(
        int $variantId,
        int $locationId,
        float $quantity,
        string $valuationMethod,
        ?int $lotId,
        ?int $serialId
    ): array {
        /** @var Builder $query */
        $query = CostLayer::query()
            ->where('product_variant_id', $variantId)
            ->where('location_id', $locationId)
            ->when($lotId, fn (Builder $q) => $q->where('lot_id', $lotId), fn (Builder $q) => $q->whereNull('lot_id'))
            ->when($serialId, fn (Builder $q) => $q->where('serial_id', $serialId), fn (Builder $q) => $q->whereNull('serial_id'))
            ->where('qty_remaining', '>', 0)
            ->lockForUpdate();

        /** @var Collection<int, CostLayer> $layers */
        $layers = $query->orderBy('id')->get();

        if ($layers->isEmpty()) {
            throw new InventoryException('Persediaan belum tersedia untuk varian tersebut di lokasi ini.');
        }

        $availableQty = $layers->sum('qty_remaining');
        if ($availableQty + self::TOLERANCE < $quantity) {
            throw new InventoryException('Persediaan tidak mencukupi untuk varian tersebut.');
        }

        if ($valuationMethod === 'moving_avg') {
            return $this->consumeWithMovingAverage($layers, $quantity);
        }

        return $this->consumeWithFifo($layers, $quantity);
    }

    private function consumeWithFifo(Collection $layers, float $quantity): array
    {
        $remaining = $quantity;
        $totalValue = 0.0;
        $segments = [];

        foreach ($layers as $layer) {
            if ($remaining <= self::TOLERANCE) {
                break;
            }

            $available = (float) $layer->qty_remaining;
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $layer->qty_remaining = $this->roundQty($available - $take);
            $layer->save();

            $value = $take * (float) $layer->unit_cost;
            $totalValue += $value;
            $segments[] = [
                'qty' => $this->roundQty($take),
                'unit_cost' => (float) $layer->unit_cost,
                'lot_id' => $layer->lot_id,
                'serial_id' => $layer->serial_id,
                'cost_layer_id' => $layer->id,
            ];

            $remaining = $this->roundQty($remaining - $take);
        }

        if ($remaining > self::TOLERANCE) {
            throw new InventoryException('Gagal mengonsumsi lapisan biaya secara FIFO.');
        }

        $unitCost = $this->roundCost($totalValue / $quantity);

        return [
            'unit_cost' => $unitCost,
            'total_value' => $this->roundCost($totalValue),
            'segments' => $segments,
        ];
    }

    private function consumeWithMovingAverage(Collection $layers, float $quantity): array
    {
        $totalQty = (float) $layers->sum('qty_remaining');
        $totalValue = (float) $layers->sum(fn (CostLayer $layer) => $layer->qty_remaining * (float) $layer->unit_cost);
        $avgCost = $this->roundCost($totalValue / $totalQty);

        $remaining = $quantity;
        $segments = [];

        foreach ($layers as $index => $layer) {
            if ($remaining <= self::TOLERANCE) {
                break;
            }

            $layerQty = (float) $layer->qty_remaining;
            if ($layerQty <= 0) {
                continue;
            }

            $share = $layerQty / $totalQty;
            $take = $this->roundQty(min($layerQty, $quantity * $share));

            if ($index === $layers->count() - 1 || $take > $remaining) {
                $take = $remaining;
            }

            if ($take <= 0) {
                continue;
            }

            $layer->qty_remaining = $this->roundQty($layerQty - $take);
            $layer->save();

            $segments[] = [
                'qty' => $take,
                'unit_cost' => $avgCost,
                'lot_id' => $layer->lot_id,
                'serial_id' => $layer->serial_id,
                'cost_layer_id' => $layer->id,
            ];

            $remaining = $this->roundQty($remaining - $take);
        }

        if ($remaining > self::TOLERANCE) {
            throw new InventoryException('Gagal mengonsumsi lapisan biaya moving average.');
        }

        $totalValueUsed = $this->roundCost($avgCost * $quantity);

        return [
            'unit_cost' => $avgCost,
            'total_value' => $totalValueUsed,
            'segments' => $segments,
        ];
    }

    private function createLine(
        InventoryTransaction $transaction,
        int $productVariantId,
        int $uomId,
        float $quantity,
        ?float $unitCost,
        ?int $lotId,
        ?int $serialId,
        string $effect
    ): InventoryTransactionLine {
        return $transaction->lines()->create([
            'product_variant_id' => $productVariantId,
            'uom_id' => $uomId,
            'quantity' => $this->roundQty($quantity),
            'unit_cost' => $unitCost !== null ? $this->roundCost($unitCost) : null,
            'lot_id' => $lotId,
            'serial_id' => $serialId,
            'effect' => $effect,
        ]);
    }

    private function createCostLayerFromReceipt(
        InventoryTransactionLine $line,
        int $locationId,
        float $quantity,
        float $unitCost,
        string $valuationMethod,
        ?int $lotId,
        ?int $serialId
    ): CostLayer {
        return CostLayer::create([
            'product_variant_id' => $line->product_variant_id,
            'location_id' => $locationId,
            'lot_id' => $lotId,
            'serial_id' => $serialId,
            'inventory_transaction_line_id' => $line->id,
            'qty_remaining' => $this->roundQty($quantity),
            'unit_cost' => $this->roundCost($unitCost),
            'valuation_method' => $valuationMethod,
        ]);
    }

    private function applyInventoryDelta(
        ProductVariant $variant,
        int $locationId,
        ?int $lotId,
        ?int $serialId,
        float $delta
    ): InventoryItem {
        $item = $this->lockInventoryItem($variant->id, $locationId, $lotId, $serialId);
        $newQty = $this->roundQty((float) $item->qty_on_hand + $delta);

        if ($newQty < -self::TOLERANCE) {
            throw new InventoryException("Persediaan {$variant->sku} tidak mencukupi di lokasi yang dipilih.");
        }

        $item->qty_on_hand = max($newQty, 0);
        $item->save();

        return $item;
    }

    private function lockInventoryItem(
        int $variantId,
        int $locationId,
        ?int $lotId,
        ?int $serialId
    ): InventoryItem {
        $query = InventoryItem::query()
            ->where('product_variant_id', $variantId)
            ->where('location_id', $locationId);

        $query = $lotId
            ? $query->where('lot_id', $lotId)
            : $query->whereNull('lot_id');

        $query = $serialId
            ? $query->where('serial_id', $serialId)
            : $query->whereNull('serial_id');

        $item = $query->lockForUpdate()->first();

        if (!$item) {
            $item = InventoryItem::create([
                'product_variant_id' => $variantId,
                'location_id' => $locationId,
                'lot_id' => $lotId,
                'serial_id' => $serialId,
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
            ]);

            $item = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->first();
        }

        return $item;
    }

    private function normalizeValuation(?string $method, ?int $locationId = null): string
    {
        $resolved = $method
            ?? $this->resolveCompanyCostingPolicy($locationId)
            ?? config('inventory.default_valuation_method', 'fifo');

        $resolved = strtolower($resolved);

        return in_array($resolved, ['fifo', 'moving_avg'], true) ? $resolved : 'fifo';
    }

    private function resolveCompanyCostingPolicy(?int $locationId): ?string
    {
        if (! $locationId) {
            return null;
        }

        /** @var Location|null $location */
        $location = Location::query()
            ->with([
                'branch:id,branch_group_id',
                'branch.branchGroup:id,company_id',
                'branch.branchGroup.company:id,costing_policy',
            ])
            ->find($locationId);

        return $location?->branch?->branchGroup?->company?->costing_policy;
    }

    private function generateTransactionNumber(): string
    {
        $prefix = strtoupper(config('inventory.transaction_number_prefix', 'INV'));
        return sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), Str::upper(Str::random(6)));
    }

    private function roundQty(float $qty): float
    {
        return round($qty, self::QTY_SCALE);
    }

    private function roundCost(float $cost): float
    {
        return round($cost, self::COST_SCALE);
    }

    private function prepareTransaction(string $type, array $payload, ?InventoryTransaction $transaction = null): InventoryTransaction
    {
        if ($transaction) {
            if ($transaction->transaction_type !== $type) {
                throw new InventoryException('Jenis transaksi tidak sesuai.');
            }

            $this->deleteTransactionInternal($transaction, true);
            $transaction->fill($payload);
            $transaction->save();

            return $transaction->refresh();
        }

        return InventoryTransaction::create(array_merge([
            'transaction_number' => $this->generateTransactionNumber(),
            'transaction_type' => $type,
        ], $payload));
    }

    private function deleteTransactionInternal(InventoryTransaction $transaction, bool $preserve): void
    {
        $transaction->loadMissing([
            'lines.productVariant',
            'lines.costLayers',
            'lines.costConsumptions.costLayer',
        ]);

        foreach ($transaction->lines as $line) {
            if ($line->effect === self::EFFECT_IN) {
                $this->reverseInboundLine($transaction, $line);
            } else {
                $this->reverseOutboundLine($transaction, $line);
            }
        }

        $transaction->lines()->delete();

        if (! $preserve) {
            $transaction->delete();
        }
    }

    private function reverseInboundLine(InventoryTransaction $transaction, InventoryTransactionLine $line): void
    {
        foreach ($line->costLayers as $layer) {
            if (abs($layer->qty_remaining - $line->quantity) > self::TOLERANCE) {
                throw new InventoryException('Tidak dapat menghapus transaksi karena stok sudah digunakan.');
            }
        }

        $variant = $line->productVariant;
        $locationId = $this->resolveLocationIdForLine($transaction, $line);

        $this->applyInventoryDelta($variant, $locationId, $line->lot_id, $line->serial_id, -$line->quantity);

        foreach ($line->costLayers as $layer) {
            $layer->delete();
        }
    }

    private function reverseOutboundLine(InventoryTransaction $transaction, InventoryTransactionLine $line): void
    {
        $variant = $line->productVariant;
        $locationId = $this->resolveLocationIdForLine($transaction, $line);

        $this->applyInventoryDelta($variant, $locationId, $line->lot_id, $line->serial_id, $line->quantity);

        foreach ($line->costConsumptions as $consumption) {
            if ($consumption->costLayer) {
                $consumption->costLayer->qty_remaining = $this->roundQty(
                    (float) $consumption->costLayer->qty_remaining + (float) $consumption->quantity
                );
                $consumption->costLayer->save();
            }
            $consumption->delete();
        }
    }

    private function resolveLocationIdForLine(InventoryTransaction $transaction, InventoryTransactionLine $line): int
    {
        if ($line->effect === self::EFFECT_OUT) {
            return $transaction->location_id_from ?? $transaction->location_id_to
                ?? throw new InventoryException('Lokasi sumber tidak ditemukan.');
        }

        return $transaction->location_id_to ?? $transaction->location_id_from
            ?? throw new InventoryException('Lokasi tujuan tidak ditemukan.');
    }
}


