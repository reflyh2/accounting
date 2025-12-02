<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;
use App\Models\Location;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class BaseInventoryController extends Controller
{
    protected function sharedFormData(): array
    {
        return [
            'locations' => $this->locationOptions(),
            'productVariants' => $this->variantOptions(),
            'valuationMethods' => $this->valuationOptions(),
            'defaultValuationMethod' => config('inventory.default_valuation_method', 'fifo'),
        ];
    }

    protected function listingData(Request $request, string $transactionType): array
    {
        $filters = [
            'search' => $request->input('search'),
            'location_id' => $request->input('location_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $query = $this->baseTransactionQuery($transactionType);

        if ($filters['search']) {
            $search = strtolower($filters['search']);
            $query->where(function (Builder $builder) use ($search) {
                $builder->whereRaw('LOWER(transaction_number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(COALESCE(source_type, \'\'::text)) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(COALESCE(notes, \'\'::text)) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($filters['location_id']) {
            $locationId = (int) $filters['location_id'];
            $query->where(function (Builder $builder) use ($locationId) {
                $builder->where('location_id_from', $locationId)
                    ->orWhere('location_id_to', $locationId);
            });
        }

        if ($filters['date_from']) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(5, min(100, $perPage));

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($transaction) => $this->transformTransaction($transaction));

        return [
            'transactions' => $transactions,
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            'perPage' => $perPage,
            'locations' => $this->locationOptions(),
        ];
    }

    protected function transactionResource(InventoryTransaction $transaction): array
    {
        $transaction->loadMissing($this->transactionRelations());

        return $this->transformTransaction($transaction);
    }

    protected function ensureTransactionType(InventoryTransaction $transaction, string $expected): void
    {
        abort_unless($transaction->transaction_type === $expected, 404);
    }

    private function baseTransactionQuery(string $transactionType): Builder
    {
        return InventoryTransaction::query()
            ->with($this->transactionRelations())
            ->where('transaction_type', $transactionType);
    }

    private function transactionRelations(): array
    {
        return [
            'locationFrom',
            'locationTo',
            'lines.productVariant.product',
            'lines.productVariant.uom',
            'lines.uom',
            'lines.lot',
            'lines.serial',
        ];
    }

    private function transformTransaction(InventoryTransaction $transaction): array
    {
        $transaction->loadMissing($this->transactionRelations());

        $lines = $transaction->lines->map(function ($line) {
            $quantity = (float) $line->quantity;
            $unitCost = $line->unit_cost !== null ? (float) $line->unit_cost : null;

            return [
                'id' => $line->id,
                'effect' => $line->effect,
                'product_variant_id' => $line->product_variant_id,
                'product_variant' => [
                    'id' => $line->productVariant?->id,
                    'sku' => $line->productVariant?->sku,
                    'product_name' => $line->productVariant?->product?->name,
                    'uom_code' => $line->productVariant?->uom?->code,
                    'attributes' => $line->productVariant?->attrs_json,
                ],
                'uom_id' => $line->uom_id,
                'uom_label' => $line->uom?->code ?? $line->productVariant?->uom?->code,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'lot' => $line->lot ? [
                    'id' => $line->lot->id,
                    'lot_code' => $line->lot->lot_code,
                ] : null,
                'serial' => $line->serial ? [
                    'id' => $line->serial->id,
                    'serial_no' => $line->serial->serial_no,
                ] : null,
                'subtotal' => $unitCost !== null ? $unitCost * $quantity : null,
            ];
        })->values();

        return [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'transaction_type' => $transaction->transaction_type,
            'transaction_date' => optional($transaction->transaction_date)?->toDateString(),
            'source_type' => $transaction->source_type,
            'source_id' => $transaction->source_id,
            'notes' => $transaction->notes,
            'location_from' => $transaction->locationFrom ? [
                'id' => $transaction->locationFrom->id,
                'code' => $transaction->locationFrom->code,
                'name' => $transaction->locationFrom->name,
            ] : null,
            'location_to' => $transaction->locationTo ? [
                'id' => $transaction->locationTo->id,
                'code' => $transaction->locationTo->code,
                'name' => $transaction->locationTo->name,
            ] : null,
            'lines' => $lines,
            'totals' => [
                'quantity' => (float) $lines->sum(fn ($line) => $line['quantity']),
                'value' => (float) $lines->sum(fn ($line) => $line['subtotal'] ?? 0),
            ],
        ];
    }

    private function locationOptions()
    {
        return Location::orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn ($location) => [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'type' => $location->type,
                'label' => trim("{$location->code} — {$location->name}"),
            ])
            ->values();
    }

    private function variantOptions()
    {
        return ProductVariant::with(['product:id,name,kind', 'uom:id,code'])
            ->where('track_inventory', true)
            ->whereHas('product', fn ($query) => $query->where('kind', 'goods'))
            ->orderBy('sku')
            ->get(['id', 'product_id', 'sku', 'uom_id'])
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'product_name' => $variant->product?->name,
                'label' => trim("{$variant->sku} — {$variant->product?->name}"),
                'uom_id' => $variant->uom_id,
                'uom_label' => $variant->uom?->code,
            ])
            ->values();
    }

    private function valuationOptions(): array
    {
        return [
            ['value' => 'fifo', 'label' => 'FIFO'],
            ['value' => 'moving_avg', 'label' => 'Moving Average'],
        ];
    }
}


