<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InventoryException;
use App\Models\InventoryTransaction;
use App\Services\Inventory\DTO\ReceiptDTO;
use App\Services\Inventory\DTO\ReceiptLineDTO;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReceiveController extends BaseInventoryController
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/Receive/Index', $this->listingData($request, 'receipt'));
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Receive/Create', array_merge($this->sharedFormData(), [
            'mode' => 'create',
            'transaction' => null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        try {
            $result = $this->inventoryService->receipt($this->makeDto($data));
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.receipts.show', $result->transaction->id)
            ->with('success', "Penerimaan {$result->transaction->transaction_number} berhasil disimpan.");
    }

    public function show(InventoryTransaction $receipt): Response
    {
        $this->ensureTransactionType($receipt, 'receipt');

        return Inertia::render('Inventory/Receive/Show', [
            'transaction' => $this->transactionResource($receipt),
        ]);
    }

    public function edit(InventoryTransaction $receipt): Response
    {
        $this->ensureTransactionType($receipt, 'receipt');

        return Inertia::render('Inventory/Receive/Edit', array_merge($this->sharedFormData(), [
            'mode' => 'edit',
            'transaction' => $this->transactionResource($receipt),
        ]));
    }

    public function update(Request $request, InventoryTransaction $receipt): RedirectResponse
    {
        $this->ensureTransactionType($receipt, 'receipt');

        $data = $this->validatePayload($request);
        try {
            $this->inventoryService->receipt($this->makeDto($data), $receipt);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.receipts.show', $receipt->id)
            ->with('success', 'Penerimaan berhasil diperbarui.');
    }

    public function destroy(InventoryTransaction $receipt): RedirectResponse
    {
        $this->ensureTransactionType($receipt, 'receipt');

        try {
            $this->inventoryService->deleteTransaction($receipt);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.receipts.index')
            ->with('success', 'Penerimaan berhasil dihapus.');
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
            'lines.*.unit_cost' => ['required', 'numeric', 'gte:0'],
        ]);
    }

    private function makeDto(array $data): ReceiptDTO
    {
        $lines = array_map(
            fn ($line) => new ReceiptLineDTO(
                (int) $line['product_variant_id'],
                (int) $line['uom_id'],
                (float) $line['quantity'],
                (float) $line['unit_cost'],
                null,
                null,
            ),
            $data['lines']
        );

        return new ReceiptDTO(
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


