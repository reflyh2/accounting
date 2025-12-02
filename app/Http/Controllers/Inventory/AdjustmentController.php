<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InventoryException;
use App\Models\InventoryTransaction;
use App\Services\Inventory\DTO\AdjustDTO;
use App\Services\Inventory\DTO\AdjustLineDTO;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdjustmentController extends BaseInventoryController
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Inventory/Adjustment/Index', $this->listingData($request, 'adjustment'));
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Adjustment/Create', array_merge($this->sharedFormData(), [
            'mode' => 'create',
            'transaction' => null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        try {
            $result = $this->inventoryService->adjust($this->makeDto($data));
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.adjustments.show', $result->transaction->id)
            ->with('success', "Penyesuaian {$result->transaction->transaction_number} berhasil disimpan.");
    }

    public function show(InventoryTransaction $adjustment): Response
    {
        $this->ensureTransactionType($adjustment, 'adjustment');

        return Inertia::render('Inventory/Adjustment/Show', [
            'transaction' => $this->transactionResource($adjustment),
        ]);
    }

    public function edit(InventoryTransaction $adjustment): Response
    {
        $this->ensureTransactionType($adjustment, 'adjustment');

        return Inertia::render('Inventory/Adjustment/Edit', array_merge($this->sharedFormData(), [
            'mode' => 'edit',
            'transaction' => $this->transactionResource($adjustment),
        ]));
    }

    public function update(Request $request, InventoryTransaction $adjustment): RedirectResponse
    {
        $this->ensureTransactionType($adjustment, 'adjustment');

        $data = $this->validatePayload($request);

        try {
            $this->inventoryService->adjust($this->makeDto($data), $adjustment);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('inventory.adjustments.show', $adjustment->id)
            ->with('success', 'Penyesuaian berhasil diperbarui.');
    }

    public function destroy(InventoryTransaction $adjustment): RedirectResponse
    {
        $this->ensureTransactionType($adjustment, 'adjustment');

        try {
            $this->inventoryService->deleteTransaction($adjustment);
        } catch (InventoryException $exception) {
            return back()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.adjustments.index')
            ->with('success', 'Penyesuaian berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'location_id' => ['required', 'exists:locations,id'],
            'valuation_method' => ['nullable', Rule::in(['fifo', 'moving_avg'])],
            'reason' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'lines.*.uom_id' => ['required', 'exists:uoms,id'],
            'lines.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
        ]);

        foreach ($data['lines'] as $index => $line) {
            if ($line['quantity'] > 0 && (!isset($line['unit_cost']) || $line['unit_cost'] === '')) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.$index.unit_cost" => 'Harga satuan wajib diisi untuk penyesuaian positif.',
                ]);
            }
        }

        return $data;
    }

    private function makeDto(array $data): AdjustDTO
    {
        $lines = array_map(
            fn ($line) => new AdjustLineDTO(
                (int) $line['product_variant_id'],
                (int) $line['uom_id'],
                (float) $line['quantity'],
                isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (float) $line['unit_cost'] : null,
                null,
                null,
            ),
            $data['lines']
        );

        return new AdjustDTO(
            Carbon::parse($data['transaction_date']),
            (int) $data['location_id'],
            $lines,
            $data['reason'] ?? null,
            $data['notes'] ?? null,
            $data['valuation_method'] ?? null,
        );
    }
}


