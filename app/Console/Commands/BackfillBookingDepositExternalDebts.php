<?php

namespace App\Console\Commands;

use App\Enums\DebtStatus;
use App\Models\Booking;
use App\Models\ExternalDebt;
use App\Models\ExternalDebtPaymentDetail;
use App\Models\SalesInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot reconciliation for ExternalDebts created before
 * commit 49868d7 fixed createExternalDebt to subtract applied
 * booking deposits. Affected invoices have:
 *   - GL correct (BOOKING_DEPOSIT_APPLIED reduced AR via journal)
 *   - ExternalDebt.amount wrong (still equals invoice.total_amount)
 *
 * Run per-tenant:
 *   php artisan tenants:run booking:backfill-deposit-debts
 *   php artisan tenants:run booking:backfill-deposit-debts --dry-run
 */
class BackfillBookingDepositExternalDebts extends Command
{
    protected $signature = 'booking:backfill-deposit-debts
        {--dry-run : Report what would change without applying}';

    protected $description = 'Reconcile ExternalDebt amounts for SalesInvoices where booking deposits were applied before the createExternalDebt fix.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun ? '=== DRY RUN ===' : '=== APPLYING CHANGES ===');

        // Find every invoice that had a booking deposit applied to it.
        $invoiceIds = Booking::query()
            ->whereNotNull('deposit_applied_to_invoice_id')
            ->where('deposit_received_amount', '>', 0)
            ->pluck('deposit_applied_to_invoice_id')
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty()) {
            $this->line('No invoices with applied booking deposits found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->line("Found {$invoiceIds->count()} invoice(s) with applied booking deposits.");

        $stats = [
            'updated' => 0,
            'deleted' => 0,
            'skipped_paid' => 0,
            'skipped_already_correct' => 0,
            'skipped_no_debt' => 0,
            'warnings' => 0,
        ];

        foreach ($invoiceIds as $invoiceId) {
            $result = $this->reconcileOne($invoiceId, $dryRun);
            $stats[$result]++;
        }

        $this->newLine();
        $this->info('Summary:');
        foreach ($stats as $key => $count) {
            $this->line("  {$key}: {$count}");
        }

        return self::SUCCESS;
    }

    private function reconcileOne(int $invoiceId, bool $dryRun): string
    {
        /** @var SalesInvoice|null $invoice */
        $invoice = SalesInvoice::find($invoiceId);
        if (! $invoice) {
            $this->warn("  Invoice #{$invoiceId}: not found, skipping.");

            return 'warnings';
        }

        $appliedDeposit = (float) Booking::query()
            ->where('deposit_applied_to_invoice_id', $invoiceId)
            ->sum('deposit_received_amount');

        $totalAmount = (float) $invoice->total_amount;
        $correctAmount = round($totalAmount - $appliedDeposit, 2);

        $debt = ExternalDebt::where('source_type', SalesInvoice::class)
            ->where('source_id', $invoiceId)
            ->where('type', 'receivable')
            ->first();

        if (! $debt) {
            // The fix already returns early when correctAmount <= 0, so a
            // missing ExternalDebt could mean either fully-covered-no-debt
            // (correct) or pre-existing user delete. Either way, nothing to fix.
            $this->line(sprintf(
                '  Invoice %s: no ExternalDebt row (total=%s applied=%s).',
                $invoice->invoice_number,
                number_format($totalAmount, 2),
                number_format($appliedDeposit, 2)
            ));

            return 'skipped_no_debt';
        }

        $currentAmount = (float) $debt->amount;
        $diff = round($currentAmount - $correctAmount, 2);

        if (abs($diff) < 0.005) {
            // Already correct (probably was re-posted after the fix landed).
            return 'skipped_already_correct';
        }

        // Don't touch debts that have payments — too risky to change amount
        // when payment_details reference the original amount allocation.
        $hasPayments = ExternalDebtPaymentDetail::where('external_debt_id', $debt->id)->exists();
        $blockingStatus = in_array($debt->status, [
            DebtStatus::PARTIALLY_PAID->value,
            DebtStatus::PAID->value,
            DebtStatus::CLOSED->value,
        ], true);

        if ($hasPayments || $blockingStatus) {
            $this->warn(sprintf(
                '  Invoice %s: ExternalDebt #%d status=%s has payments; skipping. Current=%s, would-be=%s.',
                $invoice->invoice_number,
                $debt->id,
                $debt->status,
                number_format($currentAmount, 2),
                number_format($correctAmount, 2)
            ));

            return 'skipped_paid';
        }

        if ($correctAmount <= 0) {
            // Deposit fully covered the invoice. The current fix doesn't
            // create a debt row at all in this case, so the old (buggy) row
            // should be deleted.
            $this->line(sprintf(
                '  Invoice %s: deposit fully covered; deleting ExternalDebt #%d (amount was %s).',
                $invoice->invoice_number,
                $debt->id,
                number_format($currentAmount, 2)
            ));

            if (! $dryRun) {
                DB::transaction(function () use ($debt, $invoice) {
                    if ($debt->journal_id) {
                        $journal = \App\Models\Journal::find($debt->journal_id);
                        if ($journal) {
                            $journal->journalEntries()->delete();
                            $journal->delete();
                        }
                    }
                    $debt->forceDelete();
                    $invoice->update(['external_debt_id' => null]);
                });
            }

            return 'deleted';
        }

        $exchangeRate = (float) ($debt->exchange_rate ?? 1);
        $newPrimary = round($correctAmount * $exchangeRate, 2);

        $this->line(sprintf(
            '  Invoice %s: ExternalDebt #%d amount %s -> %s (deposit applied: %s).',
            $invoice->invoice_number,
            $debt->id,
            number_format($currentAmount, 2),
            number_format($correctAmount, 2),
            number_format($appliedDeposit, 2)
        ));

        if (! $dryRun) {
            $appendedNote = sprintf(
                "\n[backfill] Deposit booking diterapkan: %s. Amount disesuaikan dari %s ke %s.",
                number_format($appliedDeposit, 2),
                number_format($currentAmount, 2),
                number_format($correctAmount, 2)
            );

            $debt->update([
                'amount' => $correctAmount,
                'primary_currency_amount' => $newPrimary,
                'notes' => trim(($debt->notes ?? '').$appendedNote),
            ]);
        }

        return 'updated';
    }
}
