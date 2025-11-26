<?php

namespace App\Listeners\Debt;

use App\Events\Debt\ExternalDebtPaymentCreated;
use App\Events\Debt\ExternalDebtPaymentUpdated;
use App\Events\Debt\ExternalDebtPaymentDeleted;
use App\Models\ExternalDebtPayment;
use App\Models\ExternalDebtPaymentDetail;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class ExternalDebtPaymentEventSubscriber
{
    public function handlePaymentCreated(ExternalDebtPaymentCreated $event): void
    {
        $this->createJournalForPayment($event->payment);
    }

    public function handlePaymentUpdated(ExternalDebtPaymentUpdated $event): void
    {
        $this->deleteJournalForPayment($event->payment);
        $this->createJournalForPayment($event->payment);
    }

    public function handlePaymentDeleted(ExternalDebtPaymentDeleted $event): void
    {
        $this->deleteJournalForPayment($event->payment);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            ExternalDebtPaymentCreated::class => 'handlePaymentCreated',
            ExternalDebtPaymentUpdated::class => 'handlePaymentUpdated',
            ExternalDebtPaymentDeleted::class => 'handlePaymentDeleted',
        ];
    }

    private function resolveJournalDate(ExternalDebtPayment $payment): string
    {
        if (in_array($payment->payment_method, ['cek', 'giro'], true)) {
            return $payment->withdrawal_date ?: $payment->payment_date;
        }
        // tunai, transfer default to payment_date
        return $payment->payment_date;
    }

    private function createJournalForPayment(ExternalDebtPayment $payment): void
    {
        $payment->loadMissing(['branch.branchGroup.company', 'currency', 'details.externalDebt', 'partner', 'account']);

        if (!$payment->account_id) {
            throw new \Exception('Akun kas/bank pembayaran tidak ditentukan.');
        }

        // Group details by debt account
        $grouped = [];
        foreach ($payment->details as $detail) {
            $debt = $detail->externalDebt;
            if (!$debt || !$debt->debt_account_id) {
                throw new \Exception('Akun hutang/piutang pada dokumen sumber tidak ditentukan.');
            }
            $grouped[$debt->debt_account_id] = ($grouped[$debt->debt_account_id] ?? 0) + (float)$detail->amount;
        }

        if (empty($grouped)) {
            throw new \Exception('Tidak ada detail pembayaran untuk membuat jurnal.');
        }

        DB::transaction(function () use ($payment, $grouped) {
            $date = $this->resolveJournalDate($payment);
            $isPayable = $payment->type === 'payable';
            $journalType = $isPayable ? 'account_payable_payment' : 'account_receivable_collection';
            $title = $isPayable ? 'Pembayaran Hutang' : 'Penerimaan Piutang';

            $journal = Journal::create([
                'branch_id' => $payment->branch_id,
                'user_global_id' => $payment->created_by,
                'journal_type' => $journalType,
                'date' => $date,
                'description' => "{$title} #{$payment->number}",
                'reference_number' => $payment->number,
            ]);

            // Create lines for each debt account
            foreach ($grouped as $debtAccountId => $amount) {
                $journal->journalEntries()->create([
                    'account_id' => $debtAccountId,
                    'debit' => $isPayable ? $amount : 0,
                    'credit' => $isPayable ? 0 : $amount,
                    'currency_id' => $payment->currency_id,
                    'exchange_rate' => $payment->exchange_rate,
                    'primary_currency_debit' => $isPayable ? $amount * $payment->exchange_rate : 0,
                    'primary_currency_credit' => $isPayable ? 0 : $amount * $payment->exchange_rate,
                ]);
            }

            // Counterpart: cash/bank line
            $total = array_sum($grouped);
            $journal->journalEntries()->create([
                'account_id' => $payment->account_id,
                'debit' => $isPayable ? 0 : $total,
                'credit' => $isPayable ? $total : 0,
                'currency_id' => $payment->currency_id,
                'exchange_rate' => $payment->exchange_rate,
                'primary_currency_debit' => $isPayable ? 0 : $total * $payment->exchange_rate,
                'primary_currency_credit' => $isPayable ? $total * $payment->exchange_rate : 0,
            ]);

            $payment->journal_id = $journal->id;
            $payment->saveQuietly();
        });
    }

    private function deleteJournalForPayment(ExternalDebtPayment $payment): void
    {
        if (!$payment->journal_id) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $journal = Journal::find($payment->journal_id);
            if (!$journal) {
                $payment->journal_id = null;
                $payment->saveQuietly();
                return;
            }

            foreach ($journal->journalEntries as $entry) {
                $entry->delete();
            }

            $payment->journal_id = null;
            $payment->saveQuietly();

            $journal->delete();
        });
    }
}


