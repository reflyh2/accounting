<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\Journal;
use App\Models\Currency;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssetObserver
{
    /**
     * Handle the Asset "created" event.
     */
    public function created(Asset $asset): void
    {
        // Only create journal for purchased assets
        if (!in_array($asset->acquisition_type, ['outright_purchase', 'financed_purchase'])) {
            return;
        }

        DB::transaction(function () use ($asset) {
            // Create journal
            $journal = Journal::create([
                'date' => $asset->purchase_date,
                'description' => "Pembelian aset: {$asset->name}",
                'journal_type' => 'asset_purchase',
                'branch_id' => $asset->branch_id,
                'user_global_id' => Auth::user()->global_id ?? null,
            ]);

            $mainCurrency = Currency::where('is_primary', true)->first();
            $exchangeRate = $mainCurrency->companyRates()->where('company_id', $asset->branch->branchGroup->company_id)->first()->exchange_rate;

            // Create journal entries
            // Debit fixed asset account
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $asset->category->fixed_asset_account_id,
                'debit' => $asset->purchase_cost,
                'credit' => 0,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => $asset->purchase_cost * $exchangeRate,
                'primary_currency_credit' => 0,
            ]);

            // Credit purchase payable account
            JournalEntry::create([
                'journal_id' => $journal->id,
                'account_id' => $asset->category->purchase_payable_account_id,
                'debit' => 0,
                'credit' => $asset->purchase_cost,
                'currency_id' => $mainCurrency->id,
                'exchange_rate' => $exchangeRate,
                'primary_currency_debit' => 0,
                'primary_currency_credit' => $asset->purchase_cost * $exchangeRate,
            ]);

            // Link journal to asset
            $asset->journal_id = $journal->id;
            $asset->saveQuietly();
        });
    }

    /**
     * Handle the Asset "updated" event.
     */
    public function updated(Asset $asset): void
    {
        // Only update journal if purchase cost changed and asset is purchased
        if (!$asset->isDirty('purchase_cost') || 
            !in_array($asset->acquisition_type, ['outright_purchase', 'financed_purchase'])) {
            return;
        }

        DB::transaction(function () use ($asset) {
            // If there's an existing journal, update its entries
            if ($asset->journal_id) {
                $journal = $asset->journal;
                
                // Update debit entry
                $journal->journalEntries()
                    ->where('account_id', $asset->category->fixed_asset_account_id)
                    ->update([
                        'debit' => $asset->purchase_cost,
                        'credit' => 0,
                    ]);

                // Update credit entry
                $journal->journalEntries()
                    ->where('account_id', $asset->category->purchase_payable_account_id)
                    ->update([
                        'debit' => 0,
                        'credit' => $asset->purchase_cost,
                    ]);
            } else {
                // Create new journal if none exists
                $this->created($asset);
            }
        });
    }

    /**
     * Handle the Asset "deleted" event.
     */
    public function deleted(Asset $asset): void
    {
        // Delete associated journal if exists
        if ($asset->journal_id) {
            $journalEntries = $asset->journal->journalEntries;

            foreach ($journalEntries as $journalEntry) {
                $journalEntry->delete();
            }

            $asset->journal->delete();
        }
    }

    /**
     * Handle the Asset "restored" event.
     */
    public function restored(Asset $asset): void
    {
        // Create a new journal for the restored asset if it's a purchased asset
        if (in_array($asset->acquisition_type, ['outright_purchase', 'financed_purchase'])) {
            $this->created($asset);
        }
    }

    /**
     * Handle the Asset "force deleted" event.
     */
    public function forceDeleted(Asset $asset): void
    {
        // Force delete associated journal if exists
        if ($asset->journal_id) {
            $journalEntries = $asset->journal->journalEntries;

            foreach ($journalEntries as $journalEntry) {
                $journalEntry->delete();
            }

            Journal::withTrashed()->find($asset->journal_id)->forceDelete();
        }
    }
} 