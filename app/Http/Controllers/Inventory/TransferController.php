<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InventoryException;
use App\Models\InventoryTransaction;
use App\Services\Inventory\DTO\TransferDTO;
use App\Services\Inventory\DTO\TransferLineDTO;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends BaseInventoryController
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/Transfer/Index', $this->listingData($request, 'transfer'));
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Transfer/Create', array_merge($this->sharedFormData(), [
            'mode' => 'create',
            'transaction' => null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        try {
            $result = $this->inventoryService->transfer($this->makeDto($data));
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.transfers.show', $result->transaction->id)
            ->with('success', "Transfer {$result->transaction->transaction_number} berhasil disimpan.");
    }

    public function show(InventoryTransaction $transfer): Response
    {
        $this->ensureTransactionType($transfer, 'transfer');

        return Inertia::render('Inventory/Transfer/Show', [
            'transaction' => $this->transactionResource($transfer),
        ]);
    }

    public function edit(InventoryTransaction $transfer): Response
    {
        $this->ensureTransactionType($transfer, 'transfer');

        return Inertia::render('Inventory/Transfer/Edit', array_merge($this->sharedFormData(), [
            'mode' => 'edit',
            'transaction' => $this->transactionResource($transfer),
        ]));
    }

    public function update(Request $request, InventoryTransaction $transfer): RedirectResponse
    {
        $this->ensureTransactionType($transfer, 'transfer');

        $data = $this->validatePayload($request);

        try {
            $this->inventoryService->transfer($this->makeDto($data), $transfer);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.transfers.show', $transfer->id)
            ->with('success', 'Transfer berhasil diperbarui.');
    }

    public function destroy(InventoryTransaction $transfer): RedirectResponse
    {
        $this->ensureTransactionType($transfer, 'transfer');

        try {
            $this->inventoryService->deleteTransaction($transfer);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.transfers.index')
            ->with('success', 'Transfer berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'transaction_date' => ['required', 'date'],
            'location_id_from' => ['required', 'different:location_id_to', 'exists:locations,id'],
            'location_id_to' => ['required', 'exists:locations,id'],
            'valuation_method' => ['nullable', Rule::in(['fifo', 'moving_avg'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'lines.*.uom_id' => ['required', 'exists:uoms,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
    }

    private function makeDto(array $data): TransferDTO
    {
        $lines = array_map(
            fn ($line) => new TransferLineDTO(
                (int) $line['product_variant_id'],
                (int) $line['uom_id'],
                (float) $line['quantity'],
                null,
                null,
            ),
            $data['lines']
        );

        return new TransferDTO(
            Carbon::parse($data['transaction_date']),
            (int) $data['location_id_from'],
            (int) $data['location_id_to'],
            $lines,
            $data['notes'] ?? null,
            $data['valuation_method'] ?? null,
        );
    }
}


