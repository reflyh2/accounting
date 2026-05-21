<?php

namespace App\Services\Purchasing;

use App\Domain\Accounting\DTO\AccountingEntry;
use App\Domain\Accounting\DTO\AccountingEventPayload;
use App\Enums\AccountingEventCode;
use App\Models\BookingLine;
use App\Models\PurchaseInvoiceLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceCost;
use App\Models\SupplierDeposit;
use App\Models\SupplierDepositConsumption;
use App\Services\Accounting\AccountingEventBus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * After a Sales Invoice posts and the obligation events have fired
 * (BOOKING_PRINCIPAL_COGS_POSTED / BOOKING_AGENT_PASSTHROUGH_POSTED /
 * createCostEntries direct journal), this router walks each obligation
 * row and consumes from open supplier deposits FIFO.
 *
 * Per draw:
 *   - lock the deposit, decrement balance, mark exhausted if zero
 *   - create SupplierDepositConsumption (polymorphic source pointer)
 *   - dispatch SUPPLIER_DEPOSIT_CONSUMED with Dr [clearing account]
 *     (per-entry account_id override) / Cr supplier_advance
 *
 * If the deposit balance is enough to fully cover the obligation, the
 * source row's settled_by_* is stamped to the latest consumption so it
 * drops out of the Generate-PI outstanding list. Partial coverage
 * leaves settled_by NULL so the remainder flows through the normal
 * PI billing path.
 */
class SupplierObligationRouter
{
    public function __construct(
        private readonly AccountingEventBus $accountingEventBus,
        private readonly SupplierDepositService $depositService,
    ) {}

    /**
     * Drive consumption for every reseller/agent booking line and every
     * supplier-payable SI direct cost on this invoice. Called from
     * SalesInvoiceService::postSoInvoice after the obligation events.
     */
    public function routeForInvoice(SalesInvoice $invoice, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        $invoice->loadMissing([
            'lines.salesOrderLine.bookingLine.booking',
            'costs.costItem',
        ]);

        foreach ($invoice->lines as $line) {
            $bookingLine = $line->salesOrderLine?->bookingLine;
            if (! $bookingLine) {
                continue;
            }

            $booking = $bookingLine->booking;
            $mode = $booking?->fulfillment_mode instanceof \BackedEnum
                ? $booking->fulfillment_mode->value
                : (string) $booking?->fulfillment_mode;

            if ($mode === 'reseller' && (float) $bookingLine->supplier_cost > 0) {
                $this->routeForSource(
                    $bookingLine,
                    BookingLine::class,
                    (int) $invoice->company_id,
                    (int) $bookingLine->supplier_partner_id,
                    (float) $bookingLine->supplier_cost,
                    AccountingEventCode::BOOKING_PRINCIPAL_COGS_POSTED->value,
                    'supplier_clearing',
                    $invoice,
                    $actor
                );
            } elseif ($mode === 'agent' && (float) $bookingLine->passthrough_amount > 0) {
                $this->routeForSource(
                    $bookingLine,
                    BookingLine::class,
                    (int) $invoice->company_id,
                    (int) $bookingLine->supplier_partner_id,
                    (float) $bookingLine->passthrough_amount,
                    AccountingEventCode::BOOKING_AGENT_PASSTHROUGH_POSTED->value,
                    'supplier_payable_passthrough',
                    $invoice,
                    $actor
                );
            }
        }

        foreach ($invoice->costs as $cost) {
            $costItem = $cost->costItem;
            if (! $costItem
                || ! $costItem->is_supplier_payable
                || ! $cost->supplier_partner_id
                || ! $costItem->credit_account_id
                || (float) $cost->amount <= 0
            ) {
                continue;
            }

            $this->routeForSiCost(
                $cost,
                (int) $invoice->company_id,
                (int) $cost->supplier_partner_id,
                (float) $cost->amount,
                (int) $costItem->credit_account_id,
                $invoice,
                $actor
            );
        }
    }

    /**
     * On-demand consumption against pre-existing obligations. Mirrors what
     * routeForInvoice does at SI post time but operates on rows the user
     * picked from the Tagihan dari Booking outstanding list, regardless of
     * when the source SI was posted.
     *
     * Use case: deposit recorded after the SI posted, or the booking
     * predated the supplier-deposit feature. The router never ran for those
     * obligations; this backfill catches them up.
     *
     * @param  int[]  $bookingLineIds
     * @param  int[]  $salesInvoiceCostIds
     * @return array{
     *     consumed_count:int, consumed_total:float, fully_settled:int, skipped:int
     * }
     */
    public function backfillConsumeForObligations(
        array $bookingLineIds,
        array $salesInvoiceCostIds,
        ?Authenticatable $actor = null
    ): array {
        $actor ??= Auth::user();

        $stats = [
            'consumed_count' => 0,
            'consumed_total' => 0.0,
            'fully_settled' => 0,
            'skipped' => 0,
        ];

        // Booking lines
        if (! empty($bookingLineIds)) {
            $lines = BookingLine::query()
                ->with('booking.convertedSalesOrder.salesInvoices')
                ->whereIn('id', $bookingLineIds)
                ->get();

            foreach ($lines as $line) {
                if ($this->backfillOneBookingLine($line, $stats, $actor)) {
                    continue;
                }
                $stats['skipped']++;
            }
        }

        // SI direct cost rows
        if (! empty($salesInvoiceCostIds)) {
            $costs = SalesInvoiceCost::query()
                ->with(['salesInvoice', 'costItem'])
                ->whereIn('id', $salesInvoiceCostIds)
                ->get();

            foreach ($costs as $cost) {
                if ($this->backfillOneSiCost($cost, $stats, $actor)) {
                    continue;
                }
                $stats['skipped']++;
            }
        }

        $stats['consumed_total'] = round($stats['consumed_total'], 2);

        return $stats;
    }

    private function backfillOneBookingLine(BookingLine $line, array &$stats, ?Authenticatable $actor): bool
    {
        if ($line->settled_by_type !== null) {
            return false;
        }

        $booking = $line->booking;
        $invoice = $booking?->convertedSalesOrder?->salesInvoices?->firstWhere('status', 'posted');
        if (! $invoice) {
            return false;
        }

        $mode = $booking->fulfillment_mode instanceof \BackedEnum
            ? $booking->fulfillment_mode->value
            : (string) $booking->fulfillment_mode;

        $alreadyConsumed = (float) SupplierDepositConsumption::query()
            ->where('consumed_by_type', BookingLine::class)
            ->where('consumed_by_id', $line->id)
            ->sum('amount');

        if ($mode === 'reseller' && (float) $line->supplier_cost > 0) {
            $remaining = round((float) $line->supplier_cost - $alreadyConsumed, 2);
            if ($remaining <= 0) {
                return false;
            }
            $beforeConsumed = $alreadyConsumed;
            $this->routeForSource(
                $line,
                BookingLine::class,
                (int) $invoice->company_id,
                (int) $line->supplier_partner_id,
                $remaining,
                AccountingEventCode::BOOKING_PRINCIPAL_COGS_POSTED->value,
                'supplier_clearing',
                $invoice,
                $actor
            );
            $this->tallyConsumed($line, BookingLine::class, $beforeConsumed, $stats);

            return true;
        }

        if ($mode === 'agent' && (float) $line->passthrough_amount > 0) {
            $remaining = round((float) $line->passthrough_amount - $alreadyConsumed, 2);
            if ($remaining <= 0) {
                return false;
            }
            $beforeConsumed = $alreadyConsumed;
            $this->routeForSource(
                $line,
                BookingLine::class,
                (int) $invoice->company_id,
                (int) $line->supplier_partner_id,
                $remaining,
                AccountingEventCode::BOOKING_AGENT_PASSTHROUGH_POSTED->value,
                'supplier_payable_passthrough',
                $invoice,
                $actor
            );
            $this->tallyConsumed($line, BookingLine::class, $beforeConsumed, $stats);

            return true;
        }

        return false;
    }

    private function backfillOneSiCost(SalesInvoiceCost $cost, array &$stats, ?Authenticatable $actor): bool
    {
        if ($cost->settled_by_type !== null) {
            return false;
        }
        $invoice = $cost->salesInvoice;
        if (! $invoice || $invoice->status !== 'posted') {
            return false;
        }
        $costItem = $cost->costItem;
        if (! $costItem || ! $costItem->is_supplier_payable || ! $costItem->credit_account_id) {
            return false;
        }
        if (! $cost->supplier_partner_id) {
            return false;
        }

        $alreadyConsumed = (float) SupplierDepositConsumption::query()
            ->where('consumed_by_type', SalesInvoiceCost::class)
            ->where('consumed_by_id', $cost->id)
            ->sum('amount');

        $remaining = round((float) $cost->amount - $alreadyConsumed, 2);
        if ($remaining <= 0) {
            return false;
        }

        $beforeConsumed = $alreadyConsumed;
        $this->routeForSiCost(
            $cost,
            (int) $invoice->company_id,
            (int) $cost->supplier_partner_id,
            $remaining,
            (int) $costItem->credit_account_id,
            $invoice,
            $actor
        );
        $this->tallyConsumed($cost, SalesInvoiceCost::class, $beforeConsumed, $stats);

        return true;
    }

    /**
     * After a routeForSource/routeForSiCost call, look at how much was newly
     * consumed by querying the difference in SupplierDepositConsumption sums
     * and update the running stats.
     */
    private function tallyConsumed(
        \Illuminate\Database\Eloquent\Model $source,
        string $sourceClass,
        float $beforeConsumed,
        array &$stats
    ): void {
        $afterConsumed = (float) SupplierDepositConsumption::query()
            ->where('consumed_by_type', $sourceClass)
            ->where('consumed_by_id', $source->id)
            ->sum('amount');

        $delta = round($afterConsumed - $beforeConsumed, 2);
        if ($delta > 0) {
            $stats['consumed_count']++;
            $stats['consumed_total'] += $delta;
        }

        // Re-read the source to see if it's now fully settled.
        $fresh = $sourceClass::find($source->id);
        if ($fresh?->settled_by_type) {
            $stats['fully_settled']++;
        }
    }

    /**
     * Reverse all consumptions for an invoice's obligations. Called from
     * SalesInvoiceService::unpostSoInvoice / unpostDirectInvoice after
     * the obligation reversal events.
     */
    public function reverseForInvoice(SalesInvoice $invoice, ?Authenticatable $actor = null): void
    {
        $actor ??= Auth::user();

        $invoice->loadMissing(['lines.salesOrderLine.bookingLine', 'costs']);

        $sources = collect();
        foreach ($invoice->lines as $line) {
            if ($line->salesOrderLine?->bookingLine) {
                $sources->push([
                    'class' => BookingLine::class,
                    'id' => $line->salesOrderLine->bookingLine->id,
                ]);
            }
        }
        foreach ($invoice->costs as $cost) {
            $sources->push([
                'class' => SalesInvoiceCost::class,
                'id' => $cost->id,
            ]);
        }

        if ($sources->isEmpty()) {
            return;
        }

        // For each source row, find consumptions made against it and reverse.
        foreach ($sources as $src) {
            $consumptions = SupplierDepositConsumption::query()
                ->where('consumed_by_type', $src['class'])
                ->where('consumed_by_id', $src['id'])
                ->get();

            foreach ($consumptions as $consumption) {
                $this->reverseConsumption($consumption, $invoice, $actor);
            }
        }
    }

    /**
     * @param  Model&\Illuminate\Database\Eloquent\Concerns\HasAttributes  $source
     */
    private function routeForSource(
        Model $source,
        string $sourceClass,
        int $companyId,
        int $partnerId,
        float $obligationAmount,
        string $eventCode,
        string $clearingRole,
        SalesInvoice $invoice,
        ?Authenticatable $actor
    ): void {
        if ($source->settled_by_type !== null) {
            return; // Already settled — nothing to consume.
        }
        if ($partnerId <= 0) {
            return; // No supplier captured; route does not apply.
        }

        $clearingAccountId = $this->resolveAccountForRole($companyId, $eventCode, $clearingRole);
        if (! $clearingAccountId) {
            return;
        }

        $remaining = $obligationAmount;
        $lastConsumption = null;

        $deposits = $this->depositService->findAvailableForPartner($companyId, $partnerId);
        foreach ($deposits as $deposit) {
            if ($remaining <= 0) {
                break;
            }

            $consumption = $this->consume(
                $deposit,
                $source,
                $sourceClass,
                $remaining,
                $clearingAccountId,
                $invoice,
                $actor
            );

            if ($consumption) {
                $remaining = round($remaining - (float) $consumption->amount, 2);
                $lastConsumption = $consumption;
            }
        }

        if ($lastConsumption && $remaining < 0.005) {
            // Fully consumed — stamp settled_by to the latest consumption row.
            // Partial consumption deliberately leaves settled_by NULL so the
            // remainder still surfaces in the obligation-billing outstanding list.
            $source->forceFill([
                'settled_by_type' => SupplierDepositConsumption::class,
                'settled_by_id' => $lastConsumption->id,
            ])->save();
        }
    }

    private function routeForSiCost(
        SalesInvoiceCost $cost,
        int $companyId,
        int $partnerId,
        float $obligationAmount,
        int $clearingAccountId,
        SalesInvoice $invoice,
        ?Authenticatable $actor
    ): void {
        if ($cost->settled_by_type !== null || $partnerId <= 0) {
            return;
        }

        $remaining = $obligationAmount;
        $lastConsumption = null;

        $deposits = $this->depositService->findAvailableForPartner($companyId, $partnerId);
        foreach ($deposits as $deposit) {
            if ($remaining <= 0) {
                break;
            }

            $consumption = $this->consume(
                $deposit,
                $cost,
                SalesInvoiceCost::class,
                $remaining,
                $clearingAccountId,
                $invoice,
                $actor
            );

            if ($consumption) {
                $remaining = round($remaining - (float) $consumption->amount, 2);
                $lastConsumption = $consumption;
            }
        }

        if ($lastConsumption && $remaining < 0.005) {
            $cost->forceFill([
                'settled_by_type' => SupplierDepositConsumption::class,
                'settled_by_id' => $lastConsumption->id,
            ])->save();
        }
    }

    /**
     * Draw down from a single deposit. Locks the deposit, decrements
     * balance, creates the consumption row, and dispatches the GL event.
     * Returns the created consumption (or null if the deposit was already
     * exhausted by the time the lock took hold).
     */
    private function consume(
        SupplierDeposit $deposit,
        Model $source,
        string $sourceClass,
        float $maxAmount,
        int $clearingAccountId,
        SalesInvoice $invoice,
        ?Authenticatable $actor
    ): ?SupplierDepositConsumption {
        return DB::transaction(function () use ($deposit, $source, $sourceClass, $maxAmount, $clearingAccountId, $invoice, $actor) {
            /** @var SupplierDeposit $locked */
            $locked = SupplierDeposit::query()->whereKey($deposit->id)->lockForUpdate()->first();
            $balance = (float) $locked->balance;
            if ($balance <= 0) {
                return null;
            }

            $drawAmount = min($balance, $maxAmount);
            $drawAmount = round($drawAmount, 2);
            if ($drawAmount <= 0) {
                return null;
            }

            $exchangeRate = (float) ($locked->exchange_rate ?: 1);
            $amountBase = round($drawAmount * $exchangeRate, 4);

            $consumption = SupplierDepositConsumption::create([
                'supplier_deposit_id' => $locked->id,
                'consumed_by_type' => $sourceClass,
                'consumed_by_id' => $source->id,
                'amount' => $drawAmount,
                'amount_base' => $amountBase,
                'consumed_at' => now(),
                'notes' => 'Auto-consumed from SI '.$invoice->invoice_number,
                'created_by' => $actor?->getAuthIdentifier(),
            ]);

            $newBalance = round($balance - $drawAmount, 2);
            $locked->update([
                'balance' => $newBalance,
                'status' => $newBalance < 0.005 ? 'exhausted' : 'open',
            ]);

            $this->dispatchConsumedEvent($locked, $consumption, $clearingAccountId, $invoice, $actor);

            return $consumption;
        });
    }

    private function reverseConsumption(
        SupplierDepositConsumption $consumption,
        SalesInvoice $invoice,
        ?Authenticatable $actor
    ): void {
        DB::transaction(function () use ($consumption, $invoice, $actor) {
            /** @var SupplierDeposit $deposit */
            $deposit = SupplierDeposit::query()->whereKey($consumption->supplier_deposit_id)->lockForUpdate()->first();

            $amount = (float) $consumption->amount;
            $newBalance = round((float) $deposit->balance + $amount, 2);

            $deposit->update([
                'balance' => $newBalance,
                'status' => $newBalance > 0.005 ? 'open' : $deposit->status,
            ]);

            // The original consumption journal had Dr [clearing] / Cr supplier_advance;
            // we need that clearing account to dispatch the reverse. Look it up via
            // the source's polymorphic mapping. The clearing account doesn't live on
            // the consumption row; we re-resolve it from the source the same way the
            // forward router did.
            $clearingAccountId = $this->resolveClearingAccountForSource(
                $consumption->consumed_by_type,
                $consumption->consumed_by_id,
                (int) $deposit->company_id
            );

            if ($clearingAccountId) {
                $this->dispatchConsumedReversedEvent($deposit, $consumption, $clearingAccountId, $invoice, $actor);
            }

            // Clear settled_by on the source if it pointed at this consumption.
            $sourceClass = $consumption->consumed_by_type;
            if (class_exists($sourceClass)) {
                $source = $sourceClass::find($consumption->consumed_by_id);
                if ($source
                    && $source->settled_by_type === SupplierDepositConsumption::class
                    && (int) $source->settled_by_id === (int) $consumption->id
                ) {
                    $source->forceFill([
                        'settled_by_type' => null,
                        'settled_by_id' => null,
                    ])->save();
                }
            }

            $consumption->delete();
        });
    }

    private function resolveClearingAccountForSource(string $sourceClass, int $sourceId, int $companyId): ?int
    {
        if ($sourceClass === BookingLine::class) {
            $line = BookingLine::query()->with('booking')->find($sourceId);
            $mode = $line?->booking?->fulfillment_mode instanceof \BackedEnum
                ? $line->booking->fulfillment_mode->value
                : (string) $line?->booking?->fulfillment_mode;

            if ($mode === 'reseller') {
                return $this->resolveAccountForRole(
                    $companyId,
                    AccountingEventCode::BOOKING_PRINCIPAL_COGS_POSTED->value,
                    'supplier_clearing'
                );
            }
            if ($mode === 'agent') {
                return $this->resolveAccountForRole(
                    $companyId,
                    AccountingEventCode::BOOKING_AGENT_PASSTHROUGH_POSTED->value,
                    'supplier_payable_passthrough'
                );
            }
        }

        if ($sourceClass === SalesInvoiceCost::class) {
            $cost = SalesInvoiceCost::query()->with('costItem')->find($sourceId);

            return $cost?->costItem?->credit_account_id ? (int) $cost->costItem->credit_account_id : null;
        }

        if ($sourceClass === PurchaseInvoiceLine::class) {
            $piLine = PurchaseInvoiceLine::find($sourceId);

            return $piLine?->account_id ? (int) $piLine->account_id : null;
        }

        return null;
    }

    private function dispatchConsumedEvent(
        SupplierDeposit $deposit,
        SupplierDepositConsumption $consumption,
        int $clearingAccountId,
        SalesInvoice $invoice,
        ?Authenticatable $actor
    ): void {
        $amountBase = (float) $consumption->amount_base;

        $payload = new AccountingEventPayload(
            AccountingEventCode::SUPPLIER_DEPOSIT_CONSUMED,
            $invoice->company_id,
            $invoice->branch_id,
            'sales_invoice',
            $invoice->id,
            $invoice->invoice_number.'-DEP-'.$consumption->id,
            $invoice->currency?->code ?? 'IDR',
            (float) ($invoice->exchange_rate ?: 1),
            CarbonImmutable::parse($invoice->invoice_date ?? now()),
            $actor?->getAuthIdentifier(),
        );

        $payload->setLines([
            AccountingEntry::debit('clearing', $amountBase, ['account_id' => $clearingAccountId]),
            AccountingEntry::credit('supplier_advance', $amountBase, ['account_id' => (int) $deposit->advance_account_id]),
        ]);

        $this->accountingEventBus->dispatch($payload);
    }

    private function dispatchConsumedReversedEvent(
        SupplierDeposit $deposit,
        SupplierDepositConsumption $consumption,
        int $clearingAccountId,
        SalesInvoice $invoice,
        ?Authenticatable $actor
    ): void {
        $amountBase = (float) $consumption->amount_base;

        $payload = new AccountingEventPayload(
            AccountingEventCode::SUPPLIER_DEPOSIT_CONSUMED_REVERSED,
            $invoice->company_id,
            $invoice->branch_id,
            'sales_invoice',
            $invoice->id,
            $invoice->invoice_number.'-DEP-'.$consumption->id.'-REV',
            $invoice->currency?->code ?? 'IDR',
            (float) ($invoice->exchange_rate ?: 1),
            CarbonImmutable::now(),
            $actor?->getAuthIdentifier(),
        );

        $payload->setLines([
            AccountingEntry::debit('supplier_advance', $amountBase, ['account_id' => (int) $deposit->advance_account_id]),
            AccountingEntry::credit('clearing', $amountBase, ['account_id' => $clearingAccountId]),
        ]);

        $this->accountingEventBus->dispatch($payload);
    }

    private function resolveAccountForRole(int $companyId, string $eventCode, string $role): ?int
    {
        $config = \App\Models\GlEventConfiguration::query()
            ->with(['lines' => fn ($q) => $q->where('role', $role)])
            ->where('company_id', $companyId)
            ->where('event_code', $eventCode)
            ->where('is_active', true)
            ->first();

        return $config?->lines->first()?->account_id;
    }
}
