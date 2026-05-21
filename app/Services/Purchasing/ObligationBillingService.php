<?php

namespace App\Services\Purchasing;

use App\Enums\AccountingEventCode;
use App\Enums\Documents\InvoiceStatus;
use App\Exceptions\ObligationBillingException;
use App\Models\BookingLine;
use App\Models\GlEventConfiguration;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\SalesInvoiceCost;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Generates Purchase Invoices from outstanding supplier obligations sourced
 * from bookings and SI direct costs. Phase 2 ships the reseller-booking
 * outstanding query and PI generation; agent passthrough (#2) and SI direct
 * costs (#3) hook into the same flow in later phases.
 */
class ObligationBillingService
{
    /**
     * Kind discriminators for outstanding obligations. Each kind sources the
     * billable amount from a different BookingLine field and resolves a
     * different clearing account at PI-generation time.
     */
    public const KIND_RESELLER = 'reseller_cost';

    public const KIND_AGENT = 'agent_passthrough';

    public const KIND_SI_COST = 'si_direct_cost';

    /**
     * Outstanding booking obligations for a supplier across all supported
     * kinds. Each row is normalised to {id, kind, amount, …display fields}
     * so the UI renders them in one list and PI generation reads the per-row
     * kind to pick the right clearing account.
     *
     * Common filters:
     *   - booking_lines.supplier_partner_id = $partnerId
     *   - booking_lines.settled_by_* is NULL (not yet billed via PI nor consumed via deposit)
     *   - source Sales Invoice posted (booking COGS / passthrough journal already fired)
     *
     * Per-kind filters layer on top.
     *
     * @return array<int, array<string, mixed>>
     */
    public function outstandingObligations(int $companyId, int $partnerId): array
    {
        $reseller = $this->outstandingResellerLines($companyId, $partnerId)
            ->map(fn ($line) => $this->presentLine($line, self::KIND_RESELLER, (float) $line->supplier_cost));

        $agent = $this->outstandingAgentLines($companyId, $partnerId)
            ->map(fn ($line) => $this->presentLine($line, self::KIND_AGENT, (float) $line->passthrough_amount));

        $siCosts = $this->outstandingSiCosts($companyId, $partnerId)
            ->map(fn ($cost) => $this->presentSiCost($cost));

        return $reseller->concat($agent)->concat($siCosts)
            ->sortBy('start_datetime')
            ->values()
            ->all();
    }

    /**
     * SalesInvoiceCost rows where the cost_item is flagged is_supplier_payable
     * and the supplier_partner_id matches. Pulled in alongside booking
     * obligations so the user bills all three kinds together when they
     * share a supplier.
     *
     * @return Collection<int, SalesInvoiceCost>
     */
    private function outstandingSiCosts(int $companyId, int $partnerId): Collection
    {
        return SalesInvoiceCost::query()
            ->with([
                'salesInvoice:id,invoice_number,company_id,invoice_date,status',
                'costItem:id,name,code,credit_account_id,is_supplier_payable',
                'supplierPartner:id,name,code',
            ])
            ->where('supplier_partner_id', $partnerId)
            ->where('amount', '>', 0)
            ->whereNull('settled_by_type')
            ->whereHas('costItem', fn ($q) => $q->where('is_supplier_payable', true))
            ->whereHas('salesInvoice', fn ($q) => $q
                ->where('company_id', $companyId)
                ->where('status', InvoiceStatus::POSTED->value))
            ->get();
    }

    private function presentSiCost(SalesInvoiceCost $cost): array
    {
        $invoice = $cost->salesInvoice;
        $invoiceDate = $invoice?->invoice_date;

        return [
            'id' => $cost->id,
            'kind' => self::KIND_SI_COST,
            'amount' => (float) $cost->amount,
            'booking_number' => $invoice?->invoice_number,
            'booking_subtype' => $cost->costItem?->name,
            'fulfillment_mode' => null,
            'product_name' => $cost->description ?: $cost->costItem?->name,
            'start_datetime' => $invoiceDate instanceof \Carbon\CarbonInterface
                ? $invoiceDate->toIso8601String()
                : (string) $invoiceDate,
            'end_datetime' => null,
            'qty' => 1,
            'supplier_invoice_ref' => null,
        ];
    }

    /**
     * Back-compat alias for callers that explicitly want only the
     * reseller-mode obligations as BookingLine models.
     */
    public function outstandingResellerObligations(int $companyId, int $partnerId): Collection
    {
        return $this->outstandingResellerLines($companyId, $partnerId);
    }

    private function outstandingResellerLines(int $companyId, int $partnerId): Collection
    {
        return BookingLine::query()
            ->with([
                'booking:id,booking_number,partner_id,company_id,booking_subtype,booked_at,fulfillment_mode',
                'booking.partner:id,name,code',
                'product:id,name,code',
                'supplier:id,name,code',
            ])
            ->where('supplier_partner_id', $partnerId)
            ->where('supplier_cost', '>', 0)
            ->whereNull('settled_by_type')
            ->whereHas('booking', fn ($q) => $q
                ->where('company_id', $companyId)
                ->where('fulfillment_mode', 'reseller'))
            ->whereHas('booking.convertedSalesOrder.salesInvoices', fn ($q) => $q
                ->where('status', InvoiceStatus::POSTED->value))
            ->orderBy('start_datetime')
            ->get();
    }

    private function outstandingAgentLines(int $companyId, int $partnerId): Collection
    {
        return BookingLine::query()
            ->with([
                'booking:id,booking_number,partner_id,company_id,booking_subtype,booked_at,fulfillment_mode',
                'booking.partner:id,name,code',
                'product:id,name,code',
                'supplier:id,name,code',
            ])
            ->where('supplier_partner_id', $partnerId)
            ->where('passthrough_amount', '>', 0)
            ->whereNull('settled_by_type')
            ->whereHas('booking', fn ($q) => $q
                ->where('company_id', $companyId)
                ->where('fulfillment_mode', 'agent'))
            ->whereHas('booking.convertedSalesOrder.salesInvoices', fn ($q) => $q
                ->where('status', InvoiceStatus::POSTED->value))
            ->orderBy('start_datetime')
            ->get();
    }

    private function presentLine(BookingLine $line, string $kind, float $amount): array
    {
        return [
            'id' => $line->id,
            'kind' => $kind,
            'amount' => $amount,
            'booking_number' => $line->booking?->booking_number,
            'booking_subtype' => $line->booking?->booking_subtype,
            'fulfillment_mode' => $line->booking?->fulfillment_mode instanceof \BackedEnum
                ? $line->booking->fulfillment_mode->value
                : $line->booking?->fulfillment_mode,
            'product_name' => $line->product?->name,
            'start_datetime' => $line->start_datetime?->toIso8601String(),
            'end_datetime' => $line->end_datetime?->toIso8601String(),
            'qty' => (int) $line->qty,
            'supplier_invoice_ref' => $line->supplier_invoice_ref,
        ];
    }

    /**
     * Generate a draft Purchase Invoice consolidating the given booking-line
     * obligations into PI lines. Stamps settled_by on each source so it
     * drops out of the outstanding list immediately (visible at PI draft
     * stage; only PI unpost re-opens them).
     *
     * @param  array{
     *     company_id:int, branch_id:int, partner_id:int, currency_id:int,
     *     invoice_date:string, due_date?:string, exchange_rate?:float,
     *     vendor_invoice_number?:string, notes?:string,
     * }  $header
     * @param  int[]  $bookingLineIds  reseller-cost or agent-passthrough rows
     * @param  int[]  $salesInvoiceCostIds  SI direct cost rows tagged is_supplier_payable
     */
    public function generatePurchaseInvoice(
        array $header,
        array $bookingLineIds,
        array $salesInvoiceCostIds = [],
        ?Authenticatable $actor = null
    ): PurchaseInvoice {
        $actor ??= Auth::user();

        if (empty($bookingLineIds) && empty($salesInvoiceCostIds)) {
            throw new ObligationBillingException('Pilih minimal satu obligation untuk dibuatkan PI.');
        }

        return DB::transaction(function () use ($header, $bookingLineIds, $salesInvoiceCostIds, $actor) {
            $resolved = collect();

            if (! empty($bookingLineIds)) {
                $lines = BookingLine::query()
                    ->with('booking')
                    ->whereIn('id', $bookingLineIds)
                    ->lockForUpdate()
                    ->get();

                if ($lines->count() !== count($bookingLineIds)) {
                    throw new ObligationBillingException('Beberapa baris booking tidak ditemukan.');
                }

                $resolved = $resolved->concat($lines->map(fn ($line) => $this->resolveLineForBilling(
                    $line,
                    (int) $header['company_id'],
                    (int) $header['partner_id']
                )));
            }

            if (! empty($salesInvoiceCostIds)) {
                $costs = SalesInvoiceCost::query()
                    ->with(['salesInvoice', 'costItem'])
                    ->whereIn('id', $salesInvoiceCostIds)
                    ->lockForUpdate()
                    ->get();

                if ($costs->count() !== count($salesInvoiceCostIds)) {
                    throw new ObligationBillingException('Beberapa baris biaya SI tidak ditemukan.');
                }

                $resolved = $resolved->concat($costs->map(fn ($cost) => $this->resolveSiCostForBilling(
                    $cost,
                    (int) $header['company_id'],
                    (int) $header['partner_id']
                )));
            }

            $invoiceDate = Carbon::parse($header['invoice_date']);
            $exchangeRate = (float) ($header['exchange_rate'] ?? 1);
            $subtotal = (float) $resolved->sum('amount');

            $invoice = PurchaseInvoice::create([
                'company_id' => $header['company_id'],
                'branch_id' => $header['branch_id'],
                'partner_id' => $header['partner_id'],
                'currency_id' => $header['currency_id'],
                'invoice_number' => $this->generateInvoiceNumber($header['company_id'], $header['branch_id'], $invoiceDate),
                'invoice_date' => $invoiceDate,
                'due_date' => isset($header['due_date']) ? Carbon::parse($header['due_date']) : null,
                'vendor_invoice_number' => $header['vendor_invoice_number'] ?? null,
                'exchange_rate' => $exchangeRate,
                'subtotal' => $subtotal,
                'tax_total' => 0,
                'total_amount' => $subtotal,
                'status' => InvoiceStatus::DRAFT->value,
                'notes' => $header['notes'] ?? 'Dihasilkan dari Tagihan Booking',
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $lineNumber = 1;
            foreach ($resolved as $row) {
                $source = $row['source'];
                $amount = (float) $row['amount'];
                $amountBase = $amount * $exchangeRate;

                $piLine = PurchaseInvoiceLine::create([
                    'purchase_invoice_id' => $invoice->id,
                    'line_number' => $lineNumber++,
                    'description' => $row['description'],
                    'quantity' => 1,
                    'quantity_base' => 1,
                    'unit_price' => $amount,
                    'line_total' => $amount,
                    'line_total_base' => $amountBase,
                    'grn_value_base' => 0,
                    'ppv_amount' => 0,
                    'tax_amount' => 0,
                    'account_id' => $row['account_id'],
                    'source_type' => $row['source_class'],
                    'source_id' => $source->id,
                ]);

                $source->forceFill([
                    'settled_by_type' => PurchaseInvoiceLine::class,
                    'settled_by_id' => $piLine->id,
                ])->save();
            }

            return $invoice->fresh(['lines', 'partner', 'currency', 'branch']);
        });
    }

    /**
     * Validate a booking line for billing and resolve its (kind, amount, account_id,
     * source, source_class, description). Throws if malformed.
     *
     * @return array{
     *     source: BookingLine, source_class: class-string, kind: string,
     *     amount: float, account_id: int, description: string
     * }
     */
    private function resolveLineForBilling(BookingLine $line, int $companyId, int $partnerId): array
    {
        if ($line->settled_by_type !== null) {
            throw new ObligationBillingException(sprintf(
                'Baris booking #%d sudah disettle.',
                $line->id
            ));
        }
        if ((int) $line->supplier_partner_id !== $partnerId) {
            throw new ObligationBillingException(sprintf(
                'Baris booking #%d milik supplier lain.',
                $line->id
            ));
        }
        if ((int) $line->booking->company_id !== $companyId) {
            throw new ObligationBillingException(sprintf(
                'Baris booking #%d milik perusahaan lain.',
                $line->id
            ));
        }

        $mode = $line->booking->fulfillment_mode instanceof \BackedEnum
            ? $line->booking->fulfillment_mode->value
            : (string) $line->booking->fulfillment_mode;

        if ($mode === 'reseller' && (float) $line->supplier_cost > 0) {
            return [
                'source' => $line,
                'source_class' => BookingLine::class,
                'kind' => self::KIND_RESELLER,
                'amount' => (float) $line->supplier_cost,
                'account_id' => $this->requireAccountForRole(
                    $companyId,
                    AccountingEventCode::BOOKING_PRINCIPAL_COGS_POSTED->value,
                    'supplier_clearing'
                ),
                'description' => $this->describeBookingLine($line, self::KIND_RESELLER),
            ];
        }

        if ($mode === 'agent' && (float) $line->passthrough_amount > 0) {
            return [
                'source' => $line,
                'source_class' => BookingLine::class,
                'kind' => self::KIND_AGENT,
                'amount' => (float) $line->passthrough_amount,
                'account_id' => $this->requireAccountForRole(
                    $companyId,
                    AccountingEventCode::BOOKING_AGENT_PASSTHROUGH_POSTED->value,
                    'supplier_payable_passthrough'
                ),
                'description' => $this->describeBookingLine($line, self::KIND_AGENT),
            ];
        }

        throw new ObligationBillingException(sprintf(
            'Baris booking #%d tidak punya obligation supplier (mode=%s, supplier_cost=%s, passthrough=%s).',
            $line->id,
            $mode,
            (string) $line->supplier_cost,
            (string) $line->passthrough_amount
        ));
    }

    /**
     * Validate a SalesInvoiceCost row for billing. Uses the CostItem's
     * credit_account_id as the debit account (that's the account the SI
     * cost entry credited at SI post time, so the PI debits it to clear).
     *
     * @return array{
     *     source: SalesInvoiceCost, source_class: class-string, kind: string,
     *     amount: float, account_id: int, description: string
     * }
     */
    private function resolveSiCostForBilling(SalesInvoiceCost $cost, int $companyId, int $partnerId): array
    {
        if ($cost->settled_by_type !== null) {
            throw new ObligationBillingException(sprintf(
                'Biaya SI #%d sudah disettle.',
                $cost->id
            ));
        }
        if ((int) $cost->supplier_partner_id !== $partnerId) {
            throw new ObligationBillingException(sprintf(
                'Biaya SI #%d milik supplier lain.',
                $cost->id
            ));
        }
        if (! $cost->costItem || ! $cost->costItem->is_supplier_payable) {
            throw new ObligationBillingException(sprintf(
                'Biaya SI #%d bukan tagihan pemasok.',
                $cost->id
            ));
        }
        if ((int) $cost->salesInvoice?->company_id !== $companyId) {
            throw new ObligationBillingException(sprintf(
                'Biaya SI #%d milik perusahaan lain.',
                $cost->id
            ));
        }
        if (! $cost->costItem->credit_account_id) {
            throw new ObligationBillingException(sprintf(
                'Biaya SI #%d: cost item belum punya credit_account untuk dibilling.',
                $cost->id
            ));
        }

        return [
            'source' => $cost,
            'source_class' => SalesInvoiceCost::class,
            'kind' => self::KIND_SI_COST,
            'amount' => (float) $cost->amount,
            'account_id' => (int) $cost->costItem->credit_account_id,
            'description' => sprintf(
                '[%s] %s%s',
                $cost->salesInvoice?->invoice_number ?? '?',
                $cost->costItem->name,
                $cost->description ? ': '.$cost->description : ''
            ),
        ];
    }

    /**
     * Like resolveAccountForRole but throws when the mapping is missing.
     */
    private function requireAccountForRole(int $companyId, string $eventCode, string $role): int
    {
        $accountId = $this->resolveAccountForRole($companyId, $eventCode, $role);
        if (! $accountId) {
            throw new ObligationBillingException(sprintf(
                'GL Event Configuration untuk role "%s" pada event %s tidak ditemukan untuk perusahaan ini.',
                $role,
                $eventCode
            ));
        }

        return $accountId;
    }

    /**
     * Look up the GL account mapped to a given role within a given event
     * configuration for a company. Used to find supplier_clearing,
     * supplier_payable_passthrough, etc. without hard-coding account names.
     */
    private function resolveAccountForRole(int $companyId, string $eventCode, string $role): ?int
    {
        $config = GlEventConfiguration::query()
            ->with(['lines' => fn ($q) => $q->where('role', $role)])
            ->where('company_id', $companyId)
            ->where('event_code', $eventCode)
            ->where('is_active', true)
            ->first();

        return $config?->lines->first()?->account_id;
    }

    private function describeBookingLine(BookingLine $line, ?string $kind = null): string
    {
        $booking = $line->booking;
        $bookingNumber = $booking?->booking_number ?? '?';
        $product = $line->product?->name ?? 'Item';
        $when = $line->start_datetime?->format('Y-m-d') ?? '';
        $tag = $kind === self::KIND_AGENT ? ' (passthrough)' : '';

        return trim(sprintf('[%s]%s %s %s', $bookingNumber, $tag, $product, $when));
    }

    /**
     * Mirror PurchaseInvoiceService::generateInvoiceNumber so obligation PIs
     * share the same numbering scheme as normal PIs. TODO: extract this to a
     * shared NumberGenerator service when adding the third PI type.
     */
    private function generateInvoiceNumber(int $companyId, int $branchId, Carbon $invoiceDate): string
    {
        $config = config('purchasing.ap_invoice_numbering', []);
        $prefix = strtoupper($config['prefix'] ?? 'PINV');
        $sequencePadding = (int) ($config['sequence_padding'] ?? 5);

        $latest = PurchaseInvoice::withTrashed()
            ->where('branch_id', $branchId)
            ->whereYear('invoice_date', $invoiceDate->year)
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $nextSequence = 1;
        if ($latest) {
            $segments = explode('.', $latest);
            $last = (int) (end($segments) ?: 0);
            $nextSequence = $last + 1;
        }

        $companySegment = str_pad((string) $companyId, 2, '0', STR_PAD_LEFT);
        $branchSegment = str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $yearSegment = $invoiceDate->format('y');
        $sequence = str_pad((string) $nextSequence, $sequencePadding, '0', STR_PAD_LEFT);

        return sprintf('%s.%s%s.%s.%s', $prefix, $companySegment, $branchSegment, $yearSegment, $sequence);
    }
}
