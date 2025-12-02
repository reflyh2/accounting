<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InventoryException;
use App\Models\InventoryTransaction;
use App\Services\Inventory\DTO\IssueDTO;
use App\Services\Inventory\DTO\IssueLineDTO;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShipController extends BaseInventoryController
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/Ship/Index', $this->listingData($request, 'issue'));
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Ship/Create', array_merge($this->sharedFormData(), [
            'mode' => 'create',
            'transaction' => null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        try {
            $result = $this->inventoryService->issue($this->makeDto($data));
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.shipments.show', $result->transaction->id)
            ->with('success', "Pengeluaran {$result->transaction->transaction_number} berhasil disimpan.");
    }

    public function show(InventoryTransaction $shipment): Response
    {
        $this->ensureTransactionType($shipment, 'issue');

        return Inertia::render('Inventory/Ship/Show', [
            'transaction' => $this->transactionResource($shipment),
        ]);
    }

    public function edit(InventoryTransaction $shipment): Response
    {
        $this->ensureTransactionType($shipment, 'issue');

        return Inertia::render('Inventory/Ship/Edit', array_merge($this->sharedFormData(), [
            'mode' => 'edit',
            'transaction' => $this->transactionResource($shipment),
        ]));
    }

    public function update(Request $request, InventoryTransaction $shipment): RedirectResponse
    {
        $this->ensureTransactionType($shipment, 'issue');

        $data = $this->validatePayload($request);

        try {
            $this->inventoryService->issue($this->makeDto($data), $shipment);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.shipments.show', $shipment->id)
            ->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(InventoryTransaction $shipment): RedirectResponse
    {
        $this->ensureTransactionType($shipment, 'issue');

        try {
            $this->inventoryService->deleteTransaction($shipment);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.shipments.index')
            ->with('success', 'Pengeluaran berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'transaction_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'valuation_method' => ['nullable', Rule::in(['fifo', 'moving_avg'])],
            'source_type' => ['nullable', 'string', 'max:120'],
            'source_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'lines.*.uom_id' => ['required', 'exists:uoms,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
    }

    private function makeDto(array $data): IssueDTO
    {
        $lines = array_map(
            fn ($line) => new IssueLineDTO(
                (int) $line['product_variant_id'],
                (int) $line['uom_id'],
                (float) $line['quantity'],
                null,
                null,
            ),
            $data['lines']
        );

        return new IssueDTO(
            Carbon::parse($data['transaction_date']),
            (int) $data['location_id'],
            $lines,
            $data['source_type'] ?: null,
            isset($data['source_id']) && $data['source_id'] !== '' ? (int) $data['source_id'] : null,
            $data['notes'] ?? null,
            $data['valuation_method'] ?? null,
        );
    }
}


