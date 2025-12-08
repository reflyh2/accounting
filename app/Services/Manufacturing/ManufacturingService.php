<?php

namespace App\Services\Manufacturing;

use App\Enums\AccountingEventCode;
use App\Exceptions\InventoryException;
use App\Models\ComponentIssue;
use App\Models\WorkOrder;
use App\Services\Accounting\AccountingEventBuilder;
use App\Services\Accounting\AccountingEventBus;
use App\Services\Inventory\DTO\IssueDTO;
use App\Services\Inventory\DTO\IssueLineDTO;
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
    ) {
    }

    public function issueComponents(ComponentIssue $componentIssue, ?Authenticatable $actor = null): ComponentIssue
    {
        if ($componentIssue->status === 'posted') {
            throw new \InvalidArgumentException('Component issue sudah diposting.');
        }

        if (!$componentIssue->location_from_id) {
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
                if (!$line->component_product_variant_id) {
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

    private function roundQuantity(float $value): float
    {
        return round($value, self::QTY_SCALE);
    }
}
