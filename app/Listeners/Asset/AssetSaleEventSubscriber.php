<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetSaleCreated;
use App\Events\Asset\AssetSaleDeleted;
use App\Events\Asset\AssetSaleUpdated;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetSaleEventSubscriber
{
    public function handleAssetSaleCreated(AssetSaleCreated $event): void
    {
        $this->createJournalForAssetSale($event->assetInvoice);
    }

    public function handleAssetSaleUpdated(AssetSaleUpdated $event): void
    {
        $this->deleteJournal($event->assetInvoice);
        $this->createJournalForAssetSale($event->assetInvoice);
    }

    public function handleAssetSaleDeleted(AssetSaleDeleted $event): void
    {
        $this->deleteJournal($event->assetInvoice);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetSaleCreated::class => 'handleAssetSaleCreated',
            AssetSaleUpdated::class => 'handleAssetSaleUpdated',
            AssetSaleDeleted::class => 'handleAssetSaleDeleted',
        ];
    }

    private function createJournalForAssetSale($assetInvoice)
    {
        DB::transaction(function () use ($assetInvoice) {
            $company = $assetInvoice->branch->branchGroup->company;
            $entries = [];

            $detailsByCategory = $assetInvoice->assetInvoiceDetails->groupBy('asset.asset_category_id');

            foreach ($detailsByCategory as $assetCategoryId => $details) {
                foreach($details as $detail) {
                    $asset = $detail->asset;
                    $assetCategory = $asset->category;
                    $salePrice = $detail->line_amount;

                    $arAccount = $assetCategory->assetSaleReceivableAccount($company);
                    $assetAccount = $assetCategory->assetAccount($company);
                    $accumulatedDepreciationAccount = $assetCategory->assetAccumulatedDepreciationAccount($company);
                    $gainOnSaleAccount = $assetCategory->assetSaleProfitAccount($company);
                    $lossOnSaleAccount = $assetCategory->assetSaleLossAccount($company);

                    if (!$arAccount) {
                        throw new \Exception("Akun Piutang Penjualan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    if (!$assetAccount) {
                        throw new \Exception("Akun Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    if (!$accumulatedDepreciationAccount) {
                        throw new \Exception("Akun Akumulasi Penyusutan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    if (!$gainOnSaleAccount) {
                        throw new \Exception("Akun Laba Penjualan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    if (!$lossOnSaleAccount) {
                        throw new \Exception("Akun Rugi Penjualan Aset tidak diatur untuk kategori: {$assetCategory->name}");
                    }

                    $accumulatedDepreciation = $asset->accumulated_depreciation;
                    $bookValue = $asset->cost_basis - $accumulatedDepreciation;
                    $gainOrLoss = $salePrice - $bookValue;

                    if ($salePrice > 0) {
                        $entries[] = [
                            'account_id' => $arAccount->id,
                            'debit' => $salePrice,
                            'credit' => 0
                        ];
                    }

                    if ($accumulatedDepreciation > 0) {
                        $entries[] = [
                            'account_id' => $accumulatedDepreciationAccount->id,
                            'debit' => $accumulatedDepreciation,
                            'credit' => 0
                        ];
                    }

                    if ($asset->cost_basis > 0) {
                        $entries[] = [
                            'account_id' => $assetAccount->id,
                            'debit' => 0,
                            'credit' => $asset->cost_basis
                        ];
                    }

                    if ($gainOrLoss > 0) {
                        $entries[] = [
                            'account_id' => $gainOnSaleAccount->id,
                            'debit' => 0,
                            'credit' => $gainOrLoss
                        ];
                    } elseif ($gainOrLoss < 0) {
                        $entries[] = [
                            'account_id' => $lossOnSaleAccount->id, 
                            'debit' => abs($gainOrLoss), 
                            'credit' => 0
                        ];
                    }
                }
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
                'journal_type' => 'asset_sale',
                'date' => $assetInvoice->invoice_date,
                'description' => "Penjualan Aset dengan Invoice #{$assetInvoice->number}",
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