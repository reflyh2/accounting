<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetRentalCreated;
use App\Events\Asset\AssetRentalDeleted;
use App\Events\Asset\AssetRentalUpdated;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetRentalEventSubscriber
{
    public function handleAssetRentalCreated(AssetRentalCreated $event): void
    {
        $this->createJournalForAssetRental($event->assetInvoice);
    }

    public function handleAssetRentalUpdated(AssetRentalUpdated $event): void
    {
        $this->deleteJournal($event->assetInvoice);
        $this->createJournalForAssetRental($event->assetInvoice);
    }

    public function handleAssetRentalDeleted(AssetRentalDeleted $event): void
    {
        $this->deleteJournal($event->assetInvoice);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetRentalCreated::class => 'handleAssetRentalCreated',
            AssetRentalUpdated::class => 'handleAssetRentalUpdated',
            AssetRentalDeleted::class => 'handleAssetRentalDeleted',
        ];
    }

    private function createJournalForAssetRental($assetInvoice)
    {
        DB::transaction(function () use ($assetInvoice) {
            $company = $assetInvoice->branch->branchGroup->company;
            $totalAmount = 0;
            $entries = [];

            $detailsByCategory = $assetInvoice->assetInvoiceDetails->groupBy('asset.asset_category_id');

            foreach ($detailsByCategory as $assetCategoryId => $details) {
                $categoryTotal = $details->sum('line_amount');
                $totalAmount += $categoryTotal;

                $assetCategory = $details->first()->asset->category;
                $prepaidAmmortizationAccount = $assetCategory->assetPrepaidAmortizationAccount($company);
                $apAccount = $assetCategory->assetAcquisitionPayableAccount($company);

                if (!$prepaidAmmortizationAccount) {
                    throw new \Exception("Akun biaya sewa dibayar dimuka tidak ditemukan untuk kategori: {$assetCategory->name} di perusahaan: {$company->name}");
                }

                if (!$apAccount) {
                    throw new \Exception("Akun hutang pembelian aset tidak ditemukan untuk kategori: {$assetCategory->name} di perusahaan: {$company->name}");
                }

                $entries[] = [
                    'account_id' => $prepaidAmmortizationAccount->id,
                    'debit' => $categoryTotal,
                    'credit' => 0,
                ];

                $entries[] = [
                    'account_id' => $apAccount->id,
                    'debit' => 0,
                    'credit' => $categoryTotal,
                ];
            }

            $combinedEntries = [];
            foreach ($entries as $entry) {
                $accountId = $entry['account_id'];
                if (!isset($combinedEntries[$accountId])) {
                    $combinedEntries[$accountId] = ['account_id' => $accountId, 'debit' => 0, 'credit' => 0];
                }
                $combinedEntries[$accountId]['debit'] += $entry['debit'];
                $combinedEntries[$accountId]['credit'] += $entry['credit'];
            }
            $entries = array_values($combinedEntries);

            $journal = Journal::create([
                'branch_id' => $assetInvoice->branch_id,
                'user_global_id' => $assetInvoice->created_by,
                'journal_type' => 'asset_rental',
                'date' => $assetInvoice->invoice_date,
                'description' => "Sewa Aset dengan Invoice #{$assetInvoice->number}",
                'reference_number' => $assetInvoice->number,
            ]);

            foreach ($entries as $entry) {
                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'currency_id' => $assetInvoice->currency_id,
                    'exchange_rate' => $assetInvoice->exchange_rate,
                    'primary_currency_debit' => $entry['debit'] * $assetInvoice->exchange_rate,
                    'primary_currency_credit' => $entry['credit'] * $assetInvoice->exchange_rate,
                ]);
            }

            $assetInvoice->journal_id = $journal->id;
            $assetInvoice->saveQuietly();
        });
    }

    private function deleteJournal($assetInvoice)
    {
        if (!$assetInvoice->journal_id) {
            return;
        }

        DB::transaction(function () use ($assetInvoice) {
            $originalJournal = Journal::find($assetInvoice->journal_id);

            if (!$originalJournal) {
                return;
            }

            foreach ($originalJournal->journalEntries as $entry) {
                $entry->delete();
            }

            $assetInvoice->journal_id = null;
            $assetInvoice->saveQuietly();

            $originalJournal->delete();
        });
    }
} 