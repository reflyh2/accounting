<?php

namespace App\Observers;

use App\Models\Journal;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\AssetDepreciationEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetDepreciationObserver
{
    /**
     * Handle the AssetDepreciationEntry "created" event.
     */
    public function created(AssetDepreciationEntry $entry): void
    {
        // Only create journal if entry is processed
        if ($entry->status !== 'processed') {
            return;
        }

        $this->createJournal($entry);
    }

    /**
     * Handle the AssetDepreciationEntry "updated" event.
     */
    public function updated(AssetDepreciationEntry $entry): void
    {
        // If entry status changed
        if ($entry->isDirty('status')) {
            // If changed to processed, create journal
            if ($entry->status === 'processed') {
                $this->createJournal($entry);
                return;
            }
            
            // If changed from processed to another status, delete journal
            if ($entry->getOriginal('status') === 'processed' && $entry->journal_id) {                
                $journalEntries = $entry->journal->journalEntries;

                foreach ($journalEntries as $journalEntry) {
                    $journalEntry->delete();
                }

                $journalId = $entry->journal_id;
                $entry->journal_id = null;
                $entry->saveQuietly();

                Journal::where('id', $journalId)->delete();

                return;
            }
        }

        // If entry amount changed or entry date changed and status is processed, update journal
        if ($entry->isDirty(['amount', 'entry_date']) && $entry->status === 'processed') {
            if ($entry->journal_id) {
                $this->updateJournal($entry);
            } else {
                $this->createJournal($entry);
            }
        }
    }

    /**
     * Handle the AssetDepreciationEntry "deleted" event.
     */
    public function deleted(AssetDepreciationEntry $entry): void
    {
        // Delete associated journal if exists
        if ($entry->journal_id) {
            $journalEntries = $entry->journal->journalEntries;

            foreach ($journalEntries as $journalEntry) {
                $journalEntry->delete();
            }

            $journalId = $entry->journal_id;

            $entry->journal_id = null;
            $entry->saveQuietly();

            Journal::where('id', $journalId)->delete();
        }
    }

    /**
     * Create journal for the entry
     */
    private function createJournal(AssetDepreciationEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            // Create journal
            $journal = Journal::create([
                'date' => $entry->entry_date,
                'description' => ($entry->type === 'depreciation' ? 'Penyusutan' : 'Amortisasi') . " aset: {$entry->asset->name} periode " . $entry->period_start->format('d/m/Y') . " - " . $entry->period_end->format('d/m/Y'),
                'journal_type' => $entry->type === 'depreciation' ? 'asset_depreciation' : 'asset_amortization',
                'branch_id' => $entry->asset->branch_id,
                'user_global_id' => Auth::user()->global_id ?? null,
            ]);

            $mainCurrency = Currency::where('is_primary', true)->first();
            $exchangeRate = $mainCurrency->companyRates()
                ->where('company_id', $entry->asset->branch->branchGroup->company_id)
                ->first()->exchange_rate;

            // Create journal entries
            // Debit depreciation expense account for depreciation entries
            // Debit rent expense account for amortization entries
            $entry1 = JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $entry->debit_account_id,
                'debit' => $entry->amount,
                'credit' => 0,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => $entry->amount * $exchangeRate,
                'primary_currency_credit' => 0
            ]);

            // Credit accumulated depreciation account for depreciation entries
            // Credit prepaid rent account for amortization entries
            $entry2 = JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $entry->credit_account_id,
                'debit' => 0,
                'credit' => $entry->amount,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $entry->amount * $exchangeRate
            ]);

            // Link journal to entry
            $entry->journal_id = $journal->id;
            $entry->saveQuietly();
        });
    }

    /**
     * Update journal entries for the entry
     */
    private function updateJournal(AssetDepreciationEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $journal = $entry->journal;
            $mainCurrency = Currency::where('is_primary', true)->first();
            $exchangeRate = $mainCurrency->companyRates()
                ->where('company_id', $entry->asset->branch->branchGroup->company_id)
                ->first()->exchange_rate;

            // Update journal date
            $journal->date = $entry->entry_date;
            $journal->saveQuietly();

            // Update debit entry (expense account)
            $journal->journalEntries()
                ->where('account_id', $entry->debit_account_id)
                ->update([
                    'debit' => $entry->amount,
                    'credit' => 0,
                    'primary_currency_debit' => $entry->amount * $exchangeRate,
                    'primary_currency_credit' => 0,
                ]);

            // Update credit entry (accumulated depreciation account)
            $journal->journalEntries()
                ->where('account_id', $entry->credit_account_id)
                ->update([
                    'debit' => 0,
                    'credit' => $entry->amount,
                    'primary_currency_debit' => 0,
                    'primary_currency_credit' => $entry->amount * $exchangeRate,
                ]);
        });
    }
} 