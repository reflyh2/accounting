<?php

namespace App\Listeners\Debt;

use App\Events\Debt\InternalDebtApproved;
use App\Events\Debt\InternalDebtDeleted;
use App\Models\InternalDebt;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class InternalDebtEventSubscriber
{
    public function handleInternalDebtApproved(InternalDebtApproved $event): void
    {
        $this->createJournalsForInternalDebt($event->debt);
    }

    public function handleInternalDebtDeleted(InternalDebtDeleted $event): void
    {
        $this->deleteJournalsForInternalDebt($event->debt);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            InternalDebtApproved::class => 'handleInternalDebtApproved',
            InternalDebtDeleted::class => 'handleInternalDebtDeleted',
        ];
    }

    private function createJournalsForInternalDebt(InternalDebt $debt): void
    {
        $debt->loadMissing(['branch.branchGroup.company', 'counterpartyBranch.branchGroup.company', 'currency']);

        if (
            !$debt->debt_account_id
            || !$debt->offset_account_id
            || !$debt->counterparty_debt_account_id
            || !$debt->counterparty_offset_account_id
        ) {
            throw new \Exception('Akun-akun untuk jurnal otomatis belum lengkap.');
        }

        DB::transaction(function () use ($debt) {
            // Borrower journal: Debit offset (kas/bank), Credit debt (hutang)
            $borrowerJournal = Journal::create([
                'branch_id' => $debt->branch_id,
                'user_global_id' => $debt->created_by,
                'journal_type' => 'internal_payable',
                'date' => $debt->issue_date,
                'description' => "Persetujuan Hutang Internal (Peminjam) #{$debt->number}",
                'reference_number' => $debt->number,
            ]);

            $borrowerJournal->journalEntries()->create([
                'account_id' => $debt->offset_account_id,
                'debit' => $debt->amount,
                'credit' => 0,
                'currency_id' => $debt->currency_id,
                'exchange_rate' => $debt->exchange_rate,
                'primary_currency_debit' => $debt->amount * $debt->exchange_rate,
                'primary_currency_credit' => 0,
            ]);
            $borrowerJournal->journalEntries()->create([
                'account_id' => $debt->debt_account_id,
                'debit' => 0,
                'credit' => $debt->amount,
                'currency_id' => $debt->currency_id,
                'exchange_rate' => $debt->exchange_rate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $debt->amount * $debt->exchange_rate,
            ]);

            // Creditor journal: Debit receivable, Credit offset (kas/bank)
            $creditorJournal = Journal::create([
                'branch_id' => $debt->counterparty_branch_id,
                'user_global_id' => $debt->created_by,
                'journal_type' => 'internal_receivable',
                'date' => $debt->issue_date,
                'description' => "Persetujuan Hutang Internal (Pemberi) #{$debt->number}",
                'reference_number' => $debt->number,
            ]);

            $creditorJournal->journalEntries()->create([
                'account_id' => $debt->counterparty_debt_account_id,
                'debit' => $debt->amount,
                'credit' => 0,
                'currency_id' => $debt->currency_id,
                'exchange_rate' => $debt->exchange_rate,
                'primary_currency_debit' => $debt->amount * $debt->exchange_rate,
                'primary_currency_credit' => 0,
            ]);
            $creditorJournal->journalEntries()->create([
                'account_id' => $debt->counterparty_offset_account_id,
                'debit' => 0,
                'credit' => $debt->amount,
                'currency_id' => $debt->currency_id,
                'exchange_rate' => $debt->exchange_rate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $debt->amount * $debt->exchange_rate,
            ]);

            $debt->journal_id = $borrowerJournal->id;
            // DB column name uses a typo: 'conterparty_journal_id'
            $debt->setAttribute('conterparty_journal_id', $creditorJournal->id);
            $debt->saveQuietly();
        });
    }

    private function deleteJournalsForInternalDebt(InternalDebt $debt): void
    {
        DB::transaction(function () use ($debt) {
            // Borrower journal
            if ($debt->journal_id) {
                $journal = Journal::find($debt->journal_id);
                if ($journal) {
                    foreach ($journal->journalEntries as $entry) {
                        $entry->delete();
                    }
                    $journal->delete();
                }
                $debt->journal_id = null;
            }

            // Creditor journal (note column typo in migration)
            $counterpartyJournalId = $debt->getAttribute('conterparty_journal_id');
            if ($counterpartyJournalId) {
                $journal = Journal::find($counterpartyJournalId);
                if ($journal) {
                    foreach ($journal->journalEntries as $entry) {
                        $entry->delete();
                    }
                    $journal->delete();
                }
                $debt->setAttribute('conterparty_journal_id', null);
            }

            $debt->saveQuietly();
        });
    }
}


