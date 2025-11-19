<?php

namespace App\Listeners\Debt;

use App\Events\Debt\ExternalDebtCreated;
use App\Events\Debt\ExternalDebtUpdated;
use App\Events\Debt\ExternalDebtDeleted;
use App\Models\ExternalDebt;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class ExternalDebtEventSubscriber
{
    public function handleExternalDebtCreated(ExternalDebtCreated $event): void
    {
        $this->createJournalForExternalDebt($event->debt);
    }

    public function handleExternalDebtUpdated(ExternalDebtUpdated $event): void
    {
        $this->deleteJournalForExternalDebt($event->debt);
        $this->createJournalForExternalDebt($event->debt);
    }

    public function handleExternalDebtDeleted(ExternalDebtDeleted $event): void
    {
        $this->deleteJournalForExternalDebt($event->debt);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            ExternalDebtCreated::class => 'handleExternalDebtCreated',
            ExternalDebtUpdated::class => 'handleExternalDebtUpdated',
            ExternalDebtDeleted::class => 'handleExternalDebtDeleted',
        ];
    }

    private function createJournalForExternalDebt(ExternalDebt $debt): void
    {
        $debt->loadMissing(['branch.branchGroup.company', 'currency']);

        if (!$debt->debt_account_id) {
            throw new \Exception('Akun hutang/piutang tidak ditentukan.');
        }

        DB::transaction(function () use ($debt) {
            $debitAccountId = null;
            $creditAccountId = null;

            if ($debt->type === 'payable') {
                // Recognize a payable: Credit debt account, Debit offset (expense/asset) if provided
                $creditAccountId = $debt->debt_account_id;
                $debitAccountId = $debt->offset_account_id;
            } else {
                // Recognize a receivable: Debit debt account, Credit offset (revenue/asset) if provided
                $debitAccountId = $debt->debt_account_id;
                $creditAccountId = $debt->offset_account_id;
            }

            if (!$debitAccountId || !$creditAccountId) {
                // If offset is missing, we cannot build a balanced journal
                throw new \Exception('Akun lawan belum ditentukan untuk membuat jurnal otomatis.');
            }

            $journalType = $debt->type === 'payable' ? 'account_payable' : 'account_receivable';

            $journal = Journal::create([
                'branch_id' => $debt->branch_id,
                'user_global_id' => $debt->created_by,
                'journal_type' => $journalType,
                'date' => $debt->issue_date,
                'description' => ($debt->type === 'payable' ? 'Pengakuan Hutang' : 'Pengakuan Piutang') . " #{$debt->number}",
                'reference_number' => $debt->number,
            ]);

            // Debt account entry
            $journal->journalEntries()->create([
                'account_id' => $debt->debt_account_id,
                'debit' => $debt->type === 'receivable' ? $debt->amount : 0,
                'credit' => $debt->type === 'payable' ? $debt->amount : 0,
                'currency_id' => $debt->currency_id,
                'exchange_rate' => $debt->exchange_rate,
                'primary_currency_debit' => $debt->type === 'receivable' ? $debt->amount * $debt->exchange_rate : 0,
                'primary_currency_credit' => $debt->type === 'payable' ? $debt->amount * $debt->exchange_rate : 0,
            ]);

            // Offset entry
            $journal->journalEntries()->create([
                'account_id' => $debt->offset_account_id,
                'debit' => $debt->type === 'payable' ? $debt->amount : 0,
                'credit' => $debt->type === 'receivable' ? $debt->amount : 0,
                'currency_id' => $debt->currency_id,
                'exchange_rate' => $debt->exchange_rate,
                'primary_currency_debit' => $debt->type === 'payable' ? $debt->amount * $debt->exchange_rate : 0,
                'primary_currency_credit' => $debt->type === 'receivable' ? $debt->amount * $debt->exchange_rate : 0,
            ]);

            $debt->journal_id = $journal->id;
            $debt->saveQuietly();
        });
    }

    private function deleteJournalForExternalDebt(ExternalDebt $debt): void
    {
        if (!$debt->journal_id) {
            return;
        }

        DB::transaction(function () use ($debt) {
            $journal = Journal::find($debt->journal_id);
            if (!$journal) {
                $debt->journal_id = null;
                $debt->saveQuietly();
                return;
            }

            foreach ($journal->journalEntries as $entry) {
                $entry->delete();
            }

            $debt->journal_id = null;
            $debt->saveQuietly();

            $journal->delete();
        });
    }
}


