<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Models\BookingLine;
use App\Models\SalesInvoiceCost;
use App\Models\SalesOrderCost;
use Illuminate\Support\Facades\DB;

class BookingLineSupplierCostService
{
    /**
     * Update the supplier cost of a booking line and propagate the changes
     * to the associated SalesOrderCost and SalesInvoiceCost.
     *
     *
     * @throws BookingException
     */
    public function updateSupplierCost(BookingLine $bookingLine, float $newCost): BookingLine
    {
        if ($bookingLine->booking->status === \App\Enums\BookingStatus::HOLD->value) {
            throw new BookingException('Harga supplier tidak dapat diubah ketika booking berstatus hold.');
        }

        if ($bookingLine->settled_by_type !== null) {
            throw new BookingException('Harga supplier tidak dapat diubah karena sudah ada faktur pembelian.');
        }

        return DB::transaction(function () use ($bookingLine, $newCost): BookingLine {
            $bookingLine->update(['supplier_cost' => $newCost]);

            // Propagate to SO/SI cost rows
            $this->propagateToSalesOrderCost($bookingLine, $newCost);

            // Propagate directly to SalesInvoiceLine cost_total and gross_margin
            $this->propagateToSalesInvoiceLines($bookingLine, $newCost);

            return $bookingLine->fresh();
        });
    }

    /**
     * Propagate the supplier cost update to the SalesOrderCost and related costs/journals.
     */
    private function propagateToSalesOrderCost(BookingLine $bookingLine, float $newCost): void
    {
        $booking = $bookingLine->booking()->with('convertedSalesOrder.costs')->first();
        $salesOrder = $booking?->convertedSalesOrder;
        if (! $salesOrder) {
            return;
        }

        // Recalculate total supplier_cost from all booking lines in the booking
        $totalNewCost = (float) BookingLine::where('booking_id', $bookingLine->booking_id)
            ->sum('supplier_cost');

        $salesOrderCost = $salesOrder->costs()
            ->whereNull('cost_item_id')
            ->first();

        if ($salesOrderCost) {
            $salesOrderCost->update(['amount' => $totalNewCost]);
            $this->propagateToSalesInvoiceCost($salesOrderCost, $totalNewCost);
        }

        // Propagate to GL Journals
        $this->propagateToJournals($bookingLine, $totalNewCost);
    }

    /**
     * Propagate the SalesOrderCost update to the SalesInvoiceCosts.
     */
    private function propagateToSalesInvoiceCost(SalesOrderCost $salesOrderCost, float $newAmount): void
    {
        $invoiceIds = DB::table('sales_invoice_sales_order')
            ->where('sales_order_id', $salesOrderCost->sales_order_id)
            ->pluck('sales_invoice_id');

        $siCosts = SalesInvoiceCost::whereIn('sales_invoice_id', $invoiceIds)
            ->whereNull('cost_item_id')
            ->get();

        foreach ($siCosts as $siCost) {
            $siCost->update(['amount' => $newAmount]);
        }
    }

    /**
     * Propagate the supplier cost update directly to SalesInvoiceLine columns.
     */
    private function propagateToSalesInvoiceLines(BookingLine $bookingLine, float $newCost): void
    {
        $soLines = \App\Models\SalesOrderLine::where('booking_line_id', $bookingLine->id)->get();
        $siLines = \App\Models\SalesInvoiceLine::whereIn('sales_order_line_id', $soLines->pluck('id'))->get();

        foreach ($siLines as $siLine) {
            $quantity = (float) $siLine->quantity_base ?: (float) $siLine->quantity;
            $unitCost = $quantity > 0 ? $newCost / $quantity : 0;
            $grossMargin = (float) $siLine->line_total_base - $newCost;

            $siLine->update([
                'unit_cost' => round($unitCost, 4),
                'cost_total' => round($newCost, 4),
                'gross_margin' => round($grossMargin, 4),
            ]);
        }
    }

    /**
     * Propagate the supplier cost update to General Ledger journal entries.
     */
    private function propagateToJournals(BookingLine $bookingLine, float $totalNewCost): void
    {
        $booking = $bookingLine->booking;
        if (! $booking?->converted_sales_order_id) {
            return;
        }

        $invoiceIds = DB::table('sales_invoice_sales_order')
            ->where('sales_order_id', $booking->converted_sales_order_id)
            ->pluck('sales_invoice_id');

        $invoices = \App\Models\SalesInvoice::whereIn('id', $invoiceIds)->get();

        foreach ($invoices as $invoice) {
            $journal = \App\Models\Journal::where('reference_number', $invoice->invoice_number)
                ->where('description', 'like', 'Booking Principal COGS Posted%')
                ->first();

            if ($journal) {
                foreach ($journal->journalEntries as $entry) {
                    $primaryAmount = $totalNewCost * (float) $entry->exchange_rate;
                    if ($entry->debit > 0) {
                        $entry->update([
                            'debit' => $totalNewCost,
                            'primary_currency_debit' => $primaryAmount,
                        ]);
                    } else {
                        $entry->update([
                            'credit' => $totalNewCost,
                            'primary_currency_credit' => $primaryAmount,
                        ]);
                    }
                }
            }
        }
    }
}
