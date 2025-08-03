<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetPurchaseCreated;
use App\Events\Asset\AssetPurchaseDeleted;
use App\Events\Asset\AssetPurchaseUpdated;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetPurchaseEventSubscriber
{
    /**
     * Handle the event.
     */
    public function handleAssetPurchaseCreated(AssetPurchaseCreated $event): void
    {
        $this->createJournalForAssetPurchase($event->assetInvoice);
    }

    public function handleAssetPurchaseUpdated(AssetPurchaseUpdated $event): void
    {
        $this->deleteJournal($event->assetInvoice);
        $this->createJournalForAssetPurchase($event->assetInvoice);
    }

    public function handleAssetPurchaseDeleted(AssetPurchaseDeleted $event): void
    {
        $this->deleteJournal($event->assetInvoice);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetPurchaseCreated::class => 'handleAssetPurchaseCreated',
            AssetPurchaseUpdated::class => 'handleAssetPurchaseUpdated',
            AssetPurchaseDeleted::class => 'handleAssetPurchaseDeleted',
        ];
    }

    private function createJournalForAssetPurchase($assetInvoice)
    {
        DB::transaction(function () use ($assetInvoice) {
            $company = $assetInvoice->branch->branchGroup->company;
            $totalAmount = 0;
            $entries = [];

            // Group details by asset category
            $detailsByCategory = $assetInvoice->assetInvoiceDetails->groupBy('asset.asset_category_id');

            foreach ($detailsByCategory as $assetCategoryId => $details) {
                $categoryTotal = $details->sum('line_amount');
                $totalAmount += $categoryTotal;

                $assetCategory = $details->first()->asset->category;
                $assetAccount = $assetCategory->assetAccount($company);
                $apAccount = $assetCategory->assetAcquisitionPayableAccount($company);

                if (!$assetAccount) {
                    throw new \Exception("Akun aset tidak ditemukan untuk kategori: {$assetCategory->name} di perusahaan: {$company->name}");
                }

                if (!$apAccount) {
                    throw new \Exception("Akun hutang pembelian aset tidak ditemukan untuk kategori: {$assetCategory->name} di perusahaan: {$company->name}");
                }

                $entries[] = [
                    'account_id' => $assetAccount->id,
                    'debit' => $categoryTotal,
                    'credit' => 0,
                ];

                $entries[] = [
                    'account_id' => $apAccount->id,
                    'debit' => 0,
                    'credit' => $categoryTotal,
                ];
            }

            // Combine entries with same account_id
            $combinedEntries = [];
            foreach ($entries as $entry) {
                $accountId = $entry['account_id'];
                if (!isset($combinedEntries[$accountId])) {
                    $combinedEntries[$accountId] = [
                        'account_id' => $accountId,
                        'debit' => 0,
                        'credit' => 0
                    ];
                }
                $combinedEntries[$accountId]['debit'] += $entry['debit'];
                $combinedEntries[$accountId]['credit'] += $entry['credit'];
            }
            $entries = array_values($combinedEntries);

            $journal = Journal::create([
                'branch_id' => $assetInvoice->branch_id,
                'user_global_id' => $assetInvoice->created_by,
                'journal_type' => 'asset_purchase',
                'date' => $assetInvoice->invoice_date,
                'description' => "Pembelian Aset dengan Invoice #{$assetInvoice->number}",
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