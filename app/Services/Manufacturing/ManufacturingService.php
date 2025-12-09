<?php

namespace App\Services\Manufacturing;

use App\Enums\AccountingEventCode;
use App\Models\ComponentIssue;
use App\Models\FinishedGoodsReceipt;
use App\Models\WorkOrder;
use App\Models\WorkOrderVariance;
use App\Services\Accounting\AccountingEventBuilder;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Inventory\DTO\IssueDTO;
use App\Services\Inventory\DTO\IssueLineDTO;
use App\Services\Inventory\DTO\ReceiptDTO;
use App\Services\Inventory\DTO\ReceiptLineDTO;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class ManufacturingService
{
    private const COST_SCALE = 4;

    private const QTY_SCALE = 3;

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AccountingEventBus $accountingEventBus,
    ) {}

    public function issueComponents(ComponentIssue $componentIssue, ?Authenticatable $actor = null): ComponentIssue
    {
        if ($componentIssue->status === 'posted') {
            throw new \InvalidArgumentException('Component issue sudah diposting.');
        }

        if (! $componentIssue->location_from_id) {
            throw new \InvalidArgumentException('Location from harus dipilih untuk posting component issue.');
        }

        $componentIssue->load([
            'componentIssueLines.componentProductVariant',
            'componentIssueLines.componentProduct',
            'componentIssueLines.uom',
            'workOrder.bom.finishedProduct',
            'company',
            'branch.branchGroup.company',
        ]);

        return DB::transaction(function () use ($componentIssue, $actor) {
            $issueLines = $componentIssue->componentIssueLines->map(function ($line) {
                if (! $line->component_product_variant_id) {
                    throw new \InvalidArgumentException('Component product variant harus dipilih untuk semua baris.');
                }

                return new IssueLineDTO(
                    $line->component_product_variant_id,
                    $line->uom_id,
                    (float) $line->quantity_issued,
                    $line->lot_id,
                    $line->serial_id,
                );
            })->toArray();

            if (empty($issueLines)) {
                throw new \InvalidArgumentException('Component issue harus memiliki minimal satu baris.');
            }

            $issueDto = new IssueDTO(
                CarbonImmutable::parse($componentIssue->issue_date),
                $componentIssue->location_from_id,
                $issueLines,
                sourceType: 'manufacturing',
                sourceId: $componentIssue->work_order_id,
                notes: $componentIssue->notes,
                valuationMethod: null, // Will use company costing policy
            );

            $result = $this->inventoryService->issue($issueDto);

            $totalMaterialCost = $this->roundCost($result->totalValue);

            $componentIssue->update([
                'inventory_transaction_id' => $result->transaction->id,
                'status' => 'posted',
                'total_material_cost' => $totalMaterialCost,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
            ]);

            $result->transaction->update([
                'source_type' => ComponentIssue::class,
                'source_id' => $componentIssue->id,
            ]);

            // Material cost is tracked in component_issue.total_material_cost
            // It will be accumulated to work order when calculating finished goods cost

            $this->dispatchIssueEvent($componentIssue, $totalMaterialCost, $actor);

            return $componentIssue->fresh([
                'componentIssueLines.componentProductVariant',
                'componentIssueLines.uom',
                'componentIssueLines.lot',
                'componentIssueLines.serial',
                'workOrder',
                'inventoryTransaction',
            ]);
        });
    }

    private function dispatchIssueEvent(
        ComponentIssue $componentIssue,
        float $totalMaterialCost,
        ?Authenticatable $actor = null
    ): void {
        if ($totalMaterialCost <= 0) {
            return;
        }

        $company = $componentIssue->company;
        $workOrder = $componentIssue->workOrder;

        $payload = AccountingEventBuilder::forDocument(AccountingEventCode::MFG_ISSUE_POSTED, [
            'company_id' => $componentIssue->company_id,
            'branch_id' => $componentIssue->branch_id,
            'document_type' => 'component_issue',
            'document_id' => $componentIssue->id,
            'document_number' => $componentIssue->issue_number,
            'currency_code' => 'IDR',
            'exchange_rate' => 1.0,
            'occurred_at' => CarbonImmutable::parse($componentIssue->issue_date),
            'actor_id' => $actor?->getAuthIdentifier(),
            'meta' => [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->wo_number,
                'inventory_transaction_id' => $componentIssue->inventory_transaction_id,
            ],
        ])->debit('wip', $this->roundCost($totalMaterialCost))
            ->credit('inventory', $this->roundCost($totalMaterialCost))
            ->build();

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function ($throwable) {
            report($throwable);
        });
    }

    private function roundCost(float $value): float
    {
        return round($value, self::COST_SCALE);
    }

    public function receiveFinishedGoods(FinishedGoodsReceipt $receipt, ?Authenticatable $actor = null): FinishedGoodsReceipt
    {
        if ($receipt->status === 'posted') {
            throw new \InvalidArgumentException('Finished goods receipt sudah diposting.');
        }

        if (! $receipt->location_to_id) {
            throw new \InvalidArgumentException('Location to harus dipilih untuk posting finished goods receipt.');
        }

        if ($receipt->quantity_good <= 0) {
            throw new \InvalidArgumentException('Quantity good harus lebih dari nol.');
        }

        $receipt->load([
            'workOrder',
            'workOrder.componentIssues',
            'finishedProductVariant',
            'company',
            'branch.branchGroup.company',
        ]);

        return DB::transaction(function () use ($receipt, $actor) {
            // Calculate total material cost from posted component issues
            $totalMaterialCost = $receipt->workOrder->componentIssues
                ->where('status', 'posted')
                ->sum('total_material_cost');

            $totalMaterialCost = $this->roundCost($totalMaterialCost);
            $laborCost = $this->roundCost((float) $receipt->labor_cost);
            $overheadCost = $this->roundCost((float) $receipt->overhead_cost);
            $totalCost = $this->roundCost($totalMaterialCost + $laborCost + $overheadCost);

            // Calculate unit cost: (material + labor + overhead) / good_qty
            $goodQty = $this->roundQuantity((float) $receipt->quantity_good);
            $unitCost = $goodQty > 0 ? $this->roundCost($totalCost / $goodQty) : 0;

            // Create inventory receipt
            $receiptLine = new ReceiptLineDTO(
                $receipt->finished_product_variant_id,
                $receipt->uom_id,
                $goodQty,
                $unitCost,
                $receipt->lot_id,
                $receipt->serial_id,
            );

            $receiptDto = new ReceiptDTO(
                CarbonImmutable::parse($receipt->receipt_date),
                $receipt->location_to_id,
                [$receiptLine],
                sourceType: 'manufacturing',
                sourceId: $receipt->work_order_id,
                notes: $receipt->notes,
                valuationMethod: null, // Will use company costing policy
            );

            $result = $this->inventoryService->receipt($receiptDto);

            // Update finished goods receipt
            $receipt->update([
                'inventory_transaction_id' => $result->transaction->id,
                'status' => 'posted',
                'total_material_cost' => $totalMaterialCost,
                'total_cost' => $totalCost,
                'unit_cost' => $unitCost,
                'posted_at' => now(),
                'posted_by' => $actor?->getAuthIdentifier(),
            ]);

            $result->transaction->update([
                'source_type' => FinishedGoodsReceipt::class,
                'source_id' => $receipt->id,
            ]);

            // Update work order quantity produced
            $receipt->workOrder->increment('quantity_produced', $goodQty);
            if ($receipt->quantity_scrap > 0) {
                $receipt->workOrder->increment('quantity_scrap', $this->roundQuantity((float) $receipt->quantity_scrap));
            }

            $this->dispatchReceiptEvent($receipt, $totalCost, $totalMaterialCost, $actor);

            return $receipt->fresh([
                'workOrder',
                'finishedProductVariant',
                'locationTo',
                'uom',
                'inventoryTransaction',
                'lot',
                'serial',
            ]);
        });
    }

    private function dispatchReceiptEvent(
        FinishedGoodsReceipt $receipt,
        float $totalCost,
        float $totalMaterialCost,
        ?Authenticatable $actor = null
    ): void {
        if ($totalCost <= 0) {
            return;
        }

        $workOrder = $receipt->workOrder;

        $payload = AccountingEventBuilder::forDocument(AccountingEventCode::MFG_RECEIPT_POSTED, [
            'company_id' => $receipt->company_id,
            'branch_id' => $receipt->branch_id,
            'document_type' => 'finished_goods_receipt',
            'document_id' => $receipt->id,
            'document_number' => $receipt->receipt_number,
            'currency_code' => 'IDR',
            'exchange_rate' => 1.0,
            'occurred_at' => CarbonImmutable::parse($receipt->receipt_date),
            'actor_id' => $actor?->getAuthIdentifier(),
            'meta' => [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->wo_number,
                'inventory_transaction_id' => $receipt->inventory_transaction_id,
                'total_material_cost' => $totalMaterialCost,
                'labor_cost' => $receipt->labor_cost,
                'overhead_cost' => $receipt->overhead_cost,
            ],
        ])->debit('inventory', $this->roundCost($totalCost))
            ->credit('wip', $this->roundCost($totalCost))
            ->build();

        rescue(function () use ($payload) {
            $this->accountingEventBus->dispatch($payload);
        }, static function ($throwable) {
            report($throwable);
        });
    }

    private function roundQuantity(float $value): float
    {
        return round($value, self::QTY_SCALE);
    }

    public function closeWorkOrder(WorkOrder $workOrder, ?Authenticatable $actor = null): WorkOrder
    {
        if ($workOrder->status !== 'completed') {
            throw new \InvalidArgumentException('Work order harus dalam status completed sebelum dapat ditutup.');
        }

        $workOrder->load([
            'company',
            'branch.branchGroup.company',
            'bom.finishedProductVariant',
            'bom.bomLines.componentProductVariant',
            'componentIssues' => function ($query) {
                $query->where('status', 'posted');
            },
            'finishedGoodsReceipts' => function ($query) {
                $query->where('status', 'posted');
            },
        ]);

        return DB::transaction(function () use ($workOrder, $actor) {
            // Calculate actual costs
            $totalActualMaterialCost = $this->roundCost(
                $workOrder->componentIssues->sum('total_material_cost')
            );

            $totalActualLaborCost = $this->roundCost(
                $workOrder->finishedGoodsReceipts->sum('labor_cost')
            );

            $totalActualOverheadCost = $this->roundCost(
                $workOrder->finishedGoodsReceipts->sum('overhead_cost')
            );

            $totalActualCost = $this->roundCost(
                $totalActualMaterialCost + $totalActualLaborCost + $totalActualOverheadCost
            );

            // Calculate standard costs (simplified - using BOM standard costs if available)
            // For now, we'll use actual costs as standard (can be enhanced later with standard cost tables)
            $totalStandardCost = $totalActualCost; // Placeholder - should come from standard cost system

            // Calculate variance
            $totalVariance = $this->roundCost($totalActualCost - $totalStandardCost);

            // Create variance records if there's a variance
            $variances = [];
            if (abs($totalVariance) > 0.0001) {
                $variance = WorkOrderVariance::create([
                    'work_order_id' => $workOrder->id,
                    'company_id' => $workOrder->company_id,
                    'branch_id' => $workOrder->branch_id,
                    'variance_type' => 'total',
                    'standard_cost' => $totalStandardCost,
                    'actual_cost' => $totalActualCost,
                    'variance_amount' => $totalVariance,
                    'description' => 'Total variance for work order closeout',
                ]);

                $variances[] = $variance;

                // Post variance accounting event
                $this->dispatchVarianceEvent($workOrder, $variance, $actor);
            }

            // Transition work order to closed status
            // Note: We need to add 'closed' to the allowed statuses
            // For now, we'll keep it as 'completed' but mark it as closed via a flag or separate field
            // Since the status enum doesn't include 'closed', we'll add a closed_at timestamp
            $workOrder->update([
                'status' => 'completed', // Keep as completed since 'closed' is not in enum
                'actual_end_date' => $workOrder->actual_end_date ?? now()->toDateString(),
            ]);

            return $workOrder->fresh([
                'company',
                'branch',
                'bom',
                'componentIssues',
                'finishedGoodsReceipts',
            ]);
        });
    }

    private function dispatchVarianceEvent(
        WorkOrder $workOrder,
        WorkOrderVariance $variance,
        ?Authenticatable $actor = null
    ): void {
        if (abs($variance->variance_amount) < 0.0001) {
            return;
        }

        $varianceAmount = abs($variance->variance_amount);
        $isUnfavorable = $variance->variance_amount > 0;

        $payload = AccountingEventBuilder::forDocument(AccountingEventCode::MFG_VARIANCE_POSTED, [
            'company_id' => $workOrder->company_id,
            'branch_id' => $workOrder->branch_id,
            'document_type' => 'work_order_variance',
            'document_id' => $variance->id,
            'document_number' => $workOrder->wo_number,
            'currency_code' => 'IDR',
            'exchange_rate' => 1.0,
            'occurred_at' => CarbonImmutable::now(),
            'actor_id' => $actor?->getAuthIdentifier(),
            'meta' => [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->wo_number,
                'variance_type' => $variance->variance_type,
                'variance_amount' => $variance->variance_amount,
                'is_unfavorable' => $isUnfavorable,
            ],
        ]);

        if ($isUnfavorable) {
            // Unfavorable variance: Dr Variance Expense, Cr WIP
            $payload->debit('variance_expense', $this->roundCost($varianceAmount))
                ->credit('wip', $this->roundCost($varianceAmount));
        } else {
            // Favorable variance: Dr WIP, Cr Variance Income
            $payload->debit('wip', $this->roundCost($varianceAmount))
                ->credit('variance_income', $this->roundCost($varianceAmount));
        }

        $builtPayload = $payload->build();

        rescue(function () use ($builtPayload, $variance) {
            $this->accountingEventBus->dispatch($builtPayload);
            $variance->update([
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        }, static function ($throwable) {
            report($throwable);
        });
    }
}
