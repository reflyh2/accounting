<?php

namespace App\Observers;

use App\Models\Journal;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\AssetMaintenanceRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetMaintenanceRecordObserver
{
    /**
     * Handle the AssetMaintenanceRecord "created" event.
     */
    public function created(AssetMaintenanceRecord $record): void
    {
        // Only create journal if status is paid
        if ($record->payment_status === 'paid') {
            $this->createJournal($record);
        }
    }

    /**
     * Handle the AssetMaintenanceRecord "updated" event.
     */
    public function updated(AssetMaintenanceRecord $record): void
    {
        // If status changed to paid
        if ($record->isDirty('payment_status') && $record->payment_status === 'paid') {
            if ($record->journal_id) {
                $this->updateJournal($record);
            } else {
                $this->createJournal($record);
            }
            return;
        }
        
        // If status changed from paid to pending
        if ($record->isDirty('payment_status') && 
            $record->getOriginal('payment_status') === 'paid' && 
            $record->payment_status === 'pending' &&
            $record->journal_id) {
            
            $journalEntries = $record->journal->journalEntries;

            foreach ($journalEntries as $journalEntry) {
                $journalEntry->delete();
            }

            $journalId = $record->journal_id;
            $record->journal_id = null;
            $record->saveQuietly();

            Journal::where('id', $journalId)->delete();
            
            return;
        }

        // If cost or payment date changed and status is paid
        if (($record->isDirty(['cost', 'payment_date']) || $record->isDirty(['credited_account_id'])) && 
            $record->payment_status === 'paid') {
            if ($record->journal_id) {
                $this->updateJournal($record);
            } else {
                $this->createJournal($record);
            }
        }
    }

    /**
     * Handle the AssetMaintenanceRecord "deleted" event.
     */
    public function deleted(AssetMaintenanceRecord $record): void
    {
        // Delete journal if exists
        if ($record->journal_id) {
            $journalEntries = $record->journal->journalEntries;

            foreach ($journalEntries as $journalEntry) {
                $journalEntry->delete();
            }

            $journalId = $record->journal_id;
            $record->journal_id = null;
            $record->saveQuietly();

            Journal::where('id', $journalId)->delete();
        }
    }

    /**
     * Create journal entry for maintenance payment
     */
    private function createJournal(AssetMaintenanceRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // Get the debit and credit accounts
            $debitAccountId = $record->maintenanceType->maintenance_cost_account_id ?? 
                              $record->asset->category->default_maintenance_expense_account_id;
            
            $creditAccountId = $record->credited_account_id ?? 
                              $record->asset->category->default_maintenance_payable_account_id;
            
            // Ensure we have valid accounts
            if (!$debitAccountId || !$creditAccountId) {
                return;
            }
            
            // Create journal
            $journal = Journal::create([
                'date' => $record->payment_date ?? $record->maintenance_date,
                'description' => "Pembayaran pemeliharaan aset: {$record->asset->name} - {$record->maintenanceType->name}",
                'journal_type' => 'asset_maintenance',
                'branch_id' => $record->asset->branch_id,
                'user_global_id' => Auth::user()->global_id ?? null,
            ]);

            $mainCurrency = Currency::where('is_primary', true)->first();
            $exchangeRate = $mainCurrency->companyRates()
                ->where('company_id', $record->asset->branch->branchGroup->company_id)
                ->first()->exchange_rate;

            // Create journal entries
            // Debit maintenance expense account
            $entry1 = JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $debitAccountId,
                'debit' => $record->cost,
                'credit' => 0,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => $record->cost * $exchangeRate,
                'primary_currency_credit' => 0
            ]);

            // Credit payable account or cash/bank account
            $entry2 = JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $record->cost,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $record->cost * $exchangeRate
            ]);

            // Link journal to record
            $record->journal_id = $journal->id;
            $record->saveQuietly();
        });
    }

    /**
     * Update journal for changed maintenance payment
     */
    private function updateJournal(AssetMaintenanceRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $journal = $record->journal;
            
            // Get the debit and credit accounts
            $debitAccountId = $record->maintenanceType->maintenance_cost_account_id ?? 
                             $record->asset->category->default_maintenance_expense_account_id;
            
            $creditAccountId = $record->credited_account_id ?? 
                               $record->asset->category->default_maintenance_payable_account_id;
            
            $mainCurrency = Currency::where('is_primary', true)->first();
            $exchangeRate = $mainCurrency->companyRates()
                ->where('company_id', $record->asset->branch->branchGroup->company_id)
                ->first()->exchange_rate;

            // Update journal date
            $journal->date = $record->payment_date ?? $record->maintenance_date;
            $journal->saveQuietly();

            // Update or create debit entry (expense account)
            $journal->journalEntries()
                ->where('debit', '>', 0)
                ->update([
                    'account_id' => $debitAccountId,
                    'debit' => $record->cost,
                    'credit' => 0,
                    'primary_currency_debit' => $record->cost * $exchangeRate,
                    'primary_currency_credit' => 0,
                ]);

            // Update or create credit entry (payable account)
            $journal->journalEntries()
                ->where('credit', '>', 0)
                ->update([
                    'account_id' => $creditAccountId,
                    'debit' => 0,
                    'credit' => $record->cost,
                    'primary_currency_debit' => 0,
                    'primary_currency_credit' => $record->cost * $exchangeRate,
                ]);
        });
    }
} 