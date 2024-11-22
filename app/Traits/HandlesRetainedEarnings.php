<?php

namespace App\Traits;

use App\Models\Journal;
use App\Models\Account;

trait HandlesRetainedEarnings
{
    protected static array $retainedEarningsTypes = [
        'pendapatan',
        'beban_pokok_penjualan',
        'beban',
        'beban_penyusutan',
        'beban_amortisasi',
        'beban_lainnya',
        'pendapatan_lainnya'
    ];

    protected function shouldCreateOrDeleteRetainedEarningsJournal(): bool
    {
        return in_array($this->account->type, self::$retainedEarningsTypes);
    }

    protected function createRetainedEarningsJournal()
    {
        if (!$this->shouldCreateOrDeleteRetainedEarningsJournal()) {
            return;
        }

        $retainedEarningsJournal = Journal::create([
            'branch_id' => $this->journal->branch_id,
            'user_global_id' => $this->journal->user_global_id,
            'date' => $this->journal->date,
            'journal_type' => 'retained_earnings',
            'reference_number' => 'RE-' . $this->journal->number,
            'description' => 'Laba Ditahan untuk Jurnal ' . $this->journal->number,
        ]);

        $retainedEarningsAccount = Account::find($this->journal->branch->branchGroup->company->default_retained_earnings_account_id);

        $retainedEarningsJournal->journalEntries()->create([
            'account_id' => $retainedEarningsAccount->id,
            'debit' => $this->debit ?? 0,
            'credit' => $this->credit ?? 0,
            'currency_id' => $this->currency_id,
            'exchange_rate' => $this->exchange_rate,
            'primary_currency_debit' => $this->primary_currency_debit ?? 0,
            'primary_currency_credit' => $this->primary_currency_credit ?? 0,
        ]);

        $this->retained_earnings_journal_id = $retainedEarningsJournal->id;
        $this->save();
    }

    protected function deleteRetainedEarningsJournal()
    {
        if (!$this->shouldCreateOrDeleteRetainedEarningsJournal()) {
            return;
        }

        $retainedEarningsJournal = Journal::find($this->retained_earnings_journal_id);
        foreach ($retainedEarningsJournal->journalEntries as $entry) {
            $entry->delete();
        }
        $retainedEarningsJournal->delete();
    }
}
