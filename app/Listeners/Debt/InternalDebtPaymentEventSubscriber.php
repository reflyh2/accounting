<?php

namespace App\Listeners\Debt;

use App\Events\Debt\InternalDebtPaymentApproved;
use App\Events\Debt\InternalDebtPaymentDeleted;
use App\Models\InternalDebtPayment;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class InternalDebtPaymentEventSubscriber
{
    public function handlePaymentApproved(InternalDebtPaymentApproved $event): void
    {
        $this->createJournalsForPayment($event->payment);
    }

    public function handlePaymentDeleted(InternalDebtPaymentDeleted $event): void
    {
        $this->deleteJournalsForPayment($event->payment);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            InternalDebtPaymentApproved::class => 'handlePaymentApproved',
            InternalDebtPaymentDeleted::class => 'handlePaymentDeleted',
        ];
    }

    private function resolveJournalDate(InternalDebtPayment $payment): string
    {
        if (in_array($payment->payment_method, ['cek', 'giro'], true)) {
            return $payment->withdrawal_date ?: $payment->payment_date;
        }
        return $payment->payment_date;
    }

    private function createJournalsForPayment(InternalDebtPayment $payment): void
    {
        $payment->loadMissing(['branch.branchGroup.company', 'currency', 'details.internalDebt', 'account']);

        if (!$payment->account_id) {
            throw new \Exception('Akun kas/bank pembayaran (peminjam) tidak ditentukan.');
        }
        if (!$payment->counterparty_account_id) {
            throw new \Exception('Akun kas/bank pihak pemberi tidak ditentukan.');
        }

        // Group amounts by debt accounts for borrower and creditor sides
        $borrowerGrouped = [];
        $creditorGrouped = [];
        foreach ($payment->details as $detail) {
            $debt = $detail->internalDebt;
            if (!$debt || !$debt->debt_account_id || !$debt->counterparty_debt_account_id) {
                throw new \Exception('Akun hutang/piutang pada dokumen sumber tidak ditentukan.');
            }
            $amount = (float) $detail->amount;
            $borrowerGrouped[$debt->debt_account_id] = ($borrowerGrouped[$debt->debt_account_id] ?? 0) + $amount;
            $creditorGrouped[$debt->counterparty_debt_account_id] = ($creditorGrouped[$debt->counterparty_debt_account_id] ?? 0) + $amount;
        }

        if (empty($borrowerGrouped)) {
            throw new \Exception('Tidak ada detail pembayaran untuk membuat jurnal.');
        }

        DB::transaction(function () use ($payment, $borrowerGrouped, $creditorGrouped) {
            $date = $this->resolveJournalDate($payment);
            $titleBorrower = 'Pembayaran Hutang Internal (Peminjam)';
            $titleCreditor = 'Penerimaan Piutang Internal (Pemberi)';

            // Borrower journal: Dr Debt (by grouped), Cr Cash/Bank
            $borrowerJournal = Journal::create([
                'branch_id' => $payment->branch_id,
                'user_global_id' => $payment->created_by,
                'journal_type' => 'internal_payable_payment',
                'date' => $date,
                'description' => "{$titleBorrower} #{$payment->number}",
                'reference_number' => $payment->number,
            ]);
            foreach ($borrowerGrouped as $debtAccountId => $amount) {
                $borrowerJournal->journalEntries()->create([
                    'account_id' => $debtAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'currency_id' => $payment->currency_id,
                    'exchange_rate' => $payment->exchange_rate,
                    'primary_currency_debit' => $amount * $payment->exchange_rate,
                    'primary_currency_credit' => 0,
                ]);
            }
            $total = array_sum($borrowerGrouped);
            $borrowerJournal->journalEntries()->create([
                'account_id' => $payment->account_id,
                'debit' => 0,
                'credit' => $total,
                'currency_id' => $payment->currency_id,
                'exchange_rate' => $payment->exchange_rate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $total * $payment->exchange_rate,
            ]);

            // Creditor journal: Dr Cash/Bank (counterparty), Cr Counterparty Receivable (by grouped)
            $creditorJournal = Journal::create([
                'branch_id' => optional($payment->details->first()?->internalDebt)->counterparty_branch_id,
                'user_global_id' => $payment->created_by,
                'journal_type' => 'internal_receivable_collection',
                'date' => $date,
                'description' => "{$titleCreditor} #{$payment->number}",
                'reference_number' => $payment->number,
            ]);
            $creditorJournal->journalEntries()->create([
                'account_id' => $payment->counterparty_account_id,
                'debit' => $total,
                'credit' => 0,
                'currency_id' => $payment->currency_id,
                'exchange_rate' => $payment->exchange_rate,
                'primary_currency_debit' => $total * $payment->exchange_rate,
                'primary_currency_credit' => 0,
            ]);
            foreach ($creditorGrouped as $counterpartyDebtAccountId => $amount) {
                $creditorJournal->journalEntries()->create([
                    'account_id' => $counterpartyDebtAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'currency_id' => $payment->currency_id,
                    'exchange_rate' => $payment->exchange_rate,
                    'primary_currency_debit' => 0,
                    'primary_currency_credit' => $amount * $payment->exchange_rate,
                ]);
            }

            $payment->journal_id = $borrowerJournal->id;
            if (property_exists($payment, 'counterparty_journal_id')) {
                $payment->counterparty_journal_id = $creditorJournal->id;
            }
            $payment->saveQuietly();
        });
    }

    private function deleteJournalsForPayment(InternalDebtPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            // Delete borrower journal if stored
            if ($payment->journal_id) {
                $journal = Journal::find($payment->journal_id);
                if ($journal) {
                    foreach ($journal->journalEntries as $entry) {
                        $entry->delete();
                    }
                    $journal->delete();
                }
                $payment->journal_id = null;
            }
            // Delete counterparty journal if stored
            if (isset($payment->counterparty_journal_id) && $payment->counterparty_journal_id) {
                $journal = Journal::find($payment->counterparty_journal_id);
                if ($journal) {
                    foreach ($journal->journalEntries as $entry) {
                        $entry->delete();
                    }
                    $journal->delete();
                }
                $payment->counterparty_journal_id = null;
            }
            $payment->saveQuietly();

            // Fallback: also delete any journals by reference number
            Journal::where('reference_number', $payment->number)->get()->each(function ($j) {
                foreach ($j->journalEntries as $e) {
                    $e->delete();
                }
                $j->delete();
            });
        });
    }
}


