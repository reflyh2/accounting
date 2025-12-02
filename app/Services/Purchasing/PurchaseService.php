<?php

namespace App\Services\Purchasing;

use App\Enums\Documents\PurchaseOrderStatus;
use App\Exceptions\PurchaseOrderException;
use App\Models\Branch;
use App\Models\Partner;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Uom;
use App\Services\Inventory\UomConversionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseService
{
    public function __construct(
        private readonly UomConversionService $uomConverter
    ) {
    }

    public function create(array $payload, ?Authenticatable $actor = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($payload, $actor) {
            $branch = Branch::with('branchGroup')->findOrFail($payload['branch_id']);
            $companyId = $branch->branchGroup?->company_id;

            if (!$companyId) {
                throw new PurchaseOrderException('Cabang tidak terhubung ke perusahaan manapun.');
            }

            $this->assertBranchBelongsToCompany($payload['company_id'] ?? null, $companyId);

            $supplier = $this->resolveSupplier((int) $payload['partner_id'], (int) $companyId);
            $orderDate = Carbon::parse($payload['order_date']);

            $purchaseOrder = PurchaseOrder::create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'partner_id' => $supplier->id,
                'currency_id' => $payload['currency_id'],
                'order_number' => $this->generateOrderNumber($companyId, $branch->id, $orderDate),
                'order_date' => $orderDate,
                'expected_date' => isset($payload['expected_date']) ? Carbon::parse($payload['expected_date']) : null,
                'supplier_reference' => $payload['supplier_reference'] ?? null,
                'payment_terms' => $payload['payment_terms'] ?? null,
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'notes' => $payload['notes'] ?? null,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $totals = $this->persistLines($purchaseOrder, $payload['lines'] ?? [], (int) $companyId);

            $purchaseOrder->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
            ]);

            return $purchaseOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'lines.variant',
                'lines.uom',
                'lines.baseUom',
            ]);
        });
    }

    public function update(PurchaseOrder $purchaseOrder, array $payload, ?Authenticatable $actor = null): PurchaseOrder
    {
        $this->assertDraft($purchaseOrder);

        $actor ??= Auth::user();

        return DB::transaction(function () use ($purchaseOrder, $payload, $actor) {
            $branch = Branch::with('branchGroup')->findOrFail($payload['branch_id']);
            $companyId = $branch->branchGroup?->company_id;

            if (!$companyId) {
                throw new PurchaseOrderException('Cabang tidak terhubung ke perusahaan manapun.');
            }

            $this->assertBranchBelongsToCompany($payload['company_id'] ?? null, $companyId);

            $supplier = $this->resolveSupplier((int) $payload['partner_id'], (int) $companyId);

            $purchaseOrder->update([
                'branch_id' => $branch->id,
                'partner_id' => $supplier->id,
                'currency_id' => $payload['currency_id'],
                'order_date' => Carbon::parse($payload['order_date']),
                'expected_date' => isset($payload['expected_date']) ? Carbon::parse($payload['expected_date']) : null,
                'supplier_reference' => $payload['supplier_reference'] ?? null,
                'payment_terms' => $payload['payment_terms'] ?? null,
                'exchange_rate' => $payload['exchange_rate'] ?? 1,
                'notes' => $payload['notes'] ?? null,
                'updated_by' => $actor?->getAuthIdentifier(),
            ]);

            $purchaseOrder->lines()->delete();
            $totals = $this->persistLines($purchaseOrder, $payload['lines'] ?? [], (int) $companyId);

            $purchaseOrder->update([
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total_amount' => $totals['total_amount'],
            ]);

            return $purchaseOrder->fresh([
                'partner',
                'branch.branchGroup.company',
                'currency',
                'lines.variant',
                'lines.uom',
                'lines.baseUom',
            ]);
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        $this->assertDraft($purchaseOrder);

        DB::transaction(function () use ($purchaseOrder): void {
            $purchaseOrder->lines()->delete();
            $purchaseOrder->delete();
        });
    }

    public function approve(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::APPROVED,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );

        $purchaseOrder->update([
            'approved_by' => $actor?->getAuthIdentifier(),
            'approved_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    public function send(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::SENT,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );

        $purchaseOrder->update([
            'sent_by' => $actor?->getAuthIdentifier(),
            'sent_at' => now(),
        ]);

        return $purchaseOrder->refresh();
    }

    public function cancel(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null, ?string $reason = null): PurchaseOrder
    {
        $actor ??= Auth::user();

        $purchaseOrder->transitionTo(
            PurchaseOrderStatus::CANCELED,
            $actor,
            $this->makerCheckerContext($purchaseOrder->company_id)
        );

        $purchaseOrder->update([
            'canceled_by' => $actor?->getAuthIdentifier(),
            'canceled_at' => now(),
            'canceled_reason' => $reason,
        ]);

        return $purchaseOrder->refresh();
    }

    public function allowedStatuses(PurchaseOrder $purchaseOrder, ?Authenticatable $actor = null): array
    {
        $actor ??= Auth::user();

        return collect(
            $purchaseOrder->allowedDocumentStatuses(
                $actor,
                $this->makerCheckerContext($purchaseOrder->company_id)
            )
        )->map(fn (PurchaseOrderStatus $status) => $status->value)
            ->values()
            ->toArray();
    }

    public function shouldEnforceMakerChecker(?int $companyId): bool
    {
        unset($companyId); // Placeholder for future per-company overrides.
        return (bool) config('purchasing.maker_checker.enforce', false);
    }

    private function makerCheckerContext(?int $companyId): array
    {
        return [
            'enforceMakerChecker' => $this->shouldEnforceMakerChecker($companyId),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{subtotal: float, tax_total: float, total_amount: float}
     */
    private function persistLines(PurchaseOrder $purchaseOrder, array $lines, int $companyId): array
    {
        if (empty($lines)) {
            throw new PurchaseOrderException('Minimal satu baris barang harus diisi.');
        }

        $lineNumber = 1;
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $variant = ProductVariant::with([
                'product.companies:id',
                'product',
                'uom',
            ])->findOrFail($line['product_variant_id']);

            if (!$variant->product->companies->pluck('id')->contains($companyId)) {
                throw new PurchaseOrderException('Produk tidak tersedia untuk perusahaan ini.');
            }

            $orderedUom = Uom::findOrFail($line['uom_id']);
            if ((int) $orderedUom->company_id !== $companyId) {
                throw new PurchaseOrderException('Satuan tidak valid untuk perusahaan ini.');
            }

            $baseUom = $variant->uom;
            if ((int) $baseUom->company_id !== $companyId) {
                throw new PurchaseOrderException('Satuan dasar varian produk tidak valid.');
            }

            $quantity = $this->roundQuantity((float) $line['quantity']);
            $unitPrice = $this->roundMoney((float) $line['unit_price']);
            $taxRate = isset($line['tax_rate']) ? (float) $line['tax_rate'] : 0.0;

            try {
                $quantityBase = $this->uomConverter->convert($quantity, $orderedUom->id, $baseUom->id);
            } catch (RuntimeException $exception) {
                throw new PurchaseOrderException($exception->getMessage(), previous: $exception);
            }

            $lineSubtotal = $this->roundMoney($quantity * $unitPrice);
            $lineTax = $this->roundMoney($lineSubtotal * ($taxRate / 100));
            $lineTotal = $this->roundMoney($lineSubtotal + $lineTax);

            $purchaseOrder->lines()->create([
                'line_number' => $lineNumber++,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'description' => $line['description'] ?? $variant->product->name,
                'uom_id' => $orderedUom->id,
                'base_uom_id' => $baseUom->id,
                'quantity' => $quantity,
                'quantity_base' => $quantityBase,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $lineTax,
                'line_total' => $lineTotal,
                'expected_date' => isset($line['expected_date']) ? Carbon::parse($line['expected_date']) : null,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        return [
            'subtotal' => $this->roundMoney($subtotal),
            'tax_total' => $this->roundMoney($taxTotal),
            'total_amount' => $this->roundMoney($subtotal + $taxTotal),
        ];
    }

    private function resolveSupplier(int $partnerId, int $companyId): Partner
    {
        /** @var Partner $partner */
        $partner = Partner::with(['roles', 'companies:id'])
            ->findOrFail($partnerId);

        $isSupplier = $partner->roles->pluck('role')->contains('supplier');
        $belongsToCompany = $partner->companies->pluck('id')->contains($companyId);

        if (!$isSupplier) {
            throw new PurchaseOrderException('Partner terpilih bukan supplier.');
        }

        if (!$belongsToCompany) {
            throw new PurchaseOrderException('Supplier tidak terdaftar pada perusahaan ini.');
        }

        return $partner;
    }

    private function assertDraft(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::DRAFT->value) {
            throw new PurchaseOrderException('Hanya PO berstatus draft yang dapat diubah.');
        }
    }

    private function generateOrderNumber(int $companyId, int $branchId, Carbon $orderDate): string
    {
        $prefix = strtoupper(config('purchasing.numbering.prefix', 'PO'));
        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $orderDate->format('y');
        $sequence = str_pad(
            (string) $this->nextSequence($branchId, $orderDate),
            (int) config('purchasing.numbering.sequence_padding', 5),
            '0',
            STR_PAD_LEFT
        );

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }

    private function nextSequence(int $branchId, Carbon $orderDate): int
    {
        $latest = PurchaseOrder::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('order_date', $orderDate->year)
            ->orderByDesc('order_number')
            ->value('order_number');

        if (!$latest) {
            return 1;
        }

        $segments = explode('.', $latest);
        $last = (int) (end($segments) ?: 0);

        return $last + 1;
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private function roundQuantity(float $value): float
    {
        return round($value, 3);
    }

    private function assertBranchBelongsToCompany(?int $requestedCompanyId, int $branchCompanyId): void
    {
        if (!$requestedCompanyId) {
            throw new PurchaseOrderException('Perusahaan wajib dipilih.');
        }

        if ((int) $requestedCompanyId !== (int) $branchCompanyId) {
            throw new PurchaseOrderException('Cabang tidak sesuai dengan perusahaan yang dipilih.');
        }
    }
}


