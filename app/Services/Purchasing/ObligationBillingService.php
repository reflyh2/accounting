<?php

namespace App\Services\Purchasing;

use App\Enums\AccountingEventCode;
use App\Enums\Documents\InvoiceStatus;
use App\Exceptions\ObligationBillingException;
use App\Models\BookingLine;
use App\Models\GlEventConfiguration;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
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
     * Outstanding reseller-booking obligations for a supplier:
     *   - booking.fulfillment_mode = reseller
     *   - booking_lines.supplier_partner_id = $partnerId
     *   - booking_lines.supplier_cost > 0
     *   - booking_lines.settled_by_* is NULL (not yet billed via PI nor consumed via deposit)
     *   - the source Sales Invoice line's invoice is posted (i.e. the booking COGS journal fired)
     *
     * Returns the BookingLine collection with eager loads sufficient for the
     * UI to render: booking, product, the SI that triggered COGS posting.
     *
     * @return Collection<int,BookingLine>
     */
    public function outstandingResellerObligations(int $companyId, int $partnerId): Collection
    {
        return BookingLine::query()
            ->with([
                'booking:id,booking_number,partner_id,company_id,booking_subtype,booked_at',
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
     * @param  int[]  $bookingLineIds  obligation rows to bill
     */
    public function generatePurchaseInvoice(
        array $header,
        array $bookingLineIds,
        ?Authenticatable $actor = null
    ): PurchaseInvoice {
        $actor ??= Auth::user();

        if (empty($bookingLineIds)) {
            throw new ObligationBillingException('Pilih minimal satu obligation untuk dibuatkan PI.');
        }

        return DB::transaction(function () use ($header, $bookingLineIds, $actor) {
            // Lock and validate the selected booking lines.
            $lines = BookingLine::query()
                ->with('booking')
                ->whereIn('id', $bookingLineIds)
                ->lockForUpdate()
                ->get();

            if ($lines->count() !== count($bookingLineIds)) {
                throw new ObligationBillingException('Beberapa baris booking tidak ditemukan.');
            }

            foreach ($lines as $line) {
                if ($line->settled_by_type !== null) {
                    throw new ObligationBillingException(sprintf(
                        'Baris booking #%d sudah disettle.',
                        $line->id
                    ));
                }
                if ((int) $line->supplier_partner_id !== (int) $header['partner_id']) {
                    throw new ObligationBillingException(sprintf(
                        'Baris booking #%d milik supplier lain.',
                        $line->id
                    ));
                }
                if ((int) $line->booking->company_id !== (int) $header['company_id']) {
                    throw new ObligationBillingException(sprintf(
                        'Baris booking #%d milik perusahaan lain.',
                        $line->id
                    ));
                }
            }

            $clearingAccountId = $this->resolveAccountForRole(
                (int) $header['company_id'],
                AccountingEventCode::BOOKING_PRINCIPAL_COGS_POSTED->value,
                'supplier_clearing'
            );

            if (! $clearingAccountId) {
                throw new ObligationBillingException(
                    'GL Event Configuration untuk supplier_clearing tidak ditemukan untuk perusahaan ini.'
                );
            }

            $invoiceDate = Carbon::parse($header['invoice_date']);
            $exchangeRate = (float) ($header['exchange_rate'] ?? 1);
            $subtotal = (float) $lines->sum('supplier_cost');

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
            foreach ($lines as $bookingLine) {
                $amount = (float) $bookingLine->supplier_cost;
                $amountBase = $amount * $exchangeRate;

                $piLine = PurchaseInvoiceLine::create([
                    'purchase_invoice_id' => $invoice->id,
                    'line_number' => $lineNumber++,
                    'description' => $this->describeBookingLine($bookingLine),
                    'quantity' => 1,
                    'quantity_base' => 1,
                    'unit_price' => $amount,
                    'line_total' => $amount,
                    'line_total_base' => $amountBase,
                    'grn_value_base' => 0,
                    'ppv_amount' => 0,
                    'tax_amount' => 0,
                    'account_id' => $clearingAccountId,
                    'source_type' => BookingLine::class,
                    'source_id' => $bookingLine->id,
                ]);

                $bookingLine->forceFill([
                    'settled_by_type' => PurchaseInvoiceLine::class,
                    'settled_by_id' => $piLine->id,
                ])->save();
            }

            return $invoice->fresh(['lines', 'partner', 'currency', 'branch']);
        });
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

    private function describeBookingLine(BookingLine $line): string
    {
        $booking = $line->booking;
        $bookingNumber = $booking?->booking_number ?? '?';
        $product = $line->product?->name ?? 'Item';
        $when = $line->start_datetime?->format('Y-m-d') ?? '';

        return trim(sprintf('[%s] %s %s', $bookingNumber, $product, $when));
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
