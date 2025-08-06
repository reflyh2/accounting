<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetInvoicePaymentCreated;
use App\Events\Asset\AssetInvoicePaymentDeleted;
use App\Events\Asset\AssetInvoicePaymentUpdated;
use App\Models\AssetCategory;
use App\Models\Journal;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

class AssetInvoicePaymentEventSubscriber
{
    public function handleAssetInvoicePaymentCreated(AssetInvoicePaymentCreated $event): void
    {
        $this->createJournalForAssetInvoicePayment($event->assetInvoicePayment);
    }

    public function handleAssetInvoicePaymentUpdated(AssetInvoicePaymentUpdated $event): void
    {
        $this->deleteJournal($event->assetInvoicePayment);
        $this->createJournalForAssetInvoicePayment($event->assetInvoicePayment);
    }

    public function handleAssetInvoicePaymentDeleted(AssetInvoicePaymentDeleted $event): void
    {
        $this->deleteJournal($event->assetInvoicePayment);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            AssetInvoicePaymentCreated::class => 'handleAssetInvoicePaymentCreated',
            AssetInvoicePaymentUpdated::class => 'handleAssetInvoicePaymentUpdated',
            AssetInvoicePaymentDeleted::class => 'handleAssetInvoicePaymentDeleted',
        ];
    }

    private function createJournalForAssetInvoicePayment($assetInvoicePayment)
    {
        DB::transaction(function () use ($assetInvoicePayment) {
            $company = $assetInvoicePayment->branch->branchGroup->company;
            $totalPayment = 0;
            $entries = [];

            // Group details by asset category
            $allocations = $assetInvoicePayment->allocations;

            foreach ($allocations as $allocation) {
                $assetInvoice = $allocation->assetInvoice;
                $assetInvoiceDetailsByCategory = $assetInvoice->assetInvoiceDetails->groupBy('asset.asset_category_id');

                $categoryTotal = [];                
                $totalAmount = 0;

                foreach ($assetInvoiceDetailsByCategory as $assetCategoryId => $assetInvoiceDetails) {
                    $categoryTotal[$assetCategoryId] = $assetInvoiceDetails->sum('line_amount');
                    $totalAmount += $categoryTotal[$assetCategoryId];
                }

                foreach ($categoryTotal as $assetCategoryId => $total) {
                    $assetCategory = AssetCategory::find($assetCategoryId);
                    $categoryAllocation = $allocation->allocated_amount * ($total / $totalAmount);
                    $totalPayment += $categoryAllocation;

                    if ($assetInvoicePayment->type === 'sales') {
                        $arAccount = $assetCategory->assetSaleReceivableAccount($company);

                        if (!$arAccount) {
                            throw new \Exception("Akun Piutang Penjualan Aset tidak ditemukan untuk kategori: {$assetCategory->name}");
                        }

                        $entries[] = [
                            'account_id' => $arAccount->id,
                            'debit' => 0,
                            'credit' => $categoryAllocation,
                        ];
                    } else {
                        $apAccount = $assetCategory->assetAcquisitionPayableAccount($company);

                        if (!$apAccount) {
                            throw new \Exception("Akun Hutang Pembelian Aset tidak ditemukan untuk kategori: {$assetCategory->name}");
                        }

                        $entries[] = [
                            'account_id' => $apAccount->id,
                            'debit' => $categoryAllocation,
                            'credit' => 0,
                        ];
                    }
                }
            }

            $entries[] = [
                'account_id' => $assetInvoicePayment->source_account_id,
                'debit' => $assetInvoicePayment->type === 'sales' ? $totalPayment : 0,
                'credit' => $assetInvoicePayment->type === 'sales' ? 0 : $totalPayment,
            ];

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
                'branch_id' => $assetInvoicePayment->branch_id,
                'user_global_id' => $assetInvoicePayment->created_by,
                'journal_type' => 'asset_invoice_payment',
                'date' => $assetInvoicePayment->payment_date,
                'description' => "Pembayaran Invoice Aset #{$assetInvoicePayment->number}",
                'reference_number' => $assetInvoicePayment->number,
            ]);

            foreach ($entries as $entry) {
                $journal->journalEntries()->create([
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'currency_id' => $assetInvoicePayment->currency_id,
                    'exchange_rate' => $assetInvoicePayment->exchange_rate,
                    'primary_currency_debit' => $entry['debit'] * $assetInvoicePayment->exchange_rate,
                    'primary_currency_credit' => $entry['credit'] * $assetInvoicePayment->exchange_rate,
                ]);
            }

            $assetInvoicePayment->journal_id = $journal->id;
            $assetInvoicePayment->saveQuietly();
        });
    }

    private function deleteJournal($assetInvoicePayment)
    {
        if (!$assetInvoicePayment->journal_id) {
            return;
        }

        DB::transaction(function () use ($assetInvoicePayment) {
            $originalJournal = Journal::find($assetInvoicePayment->journal_id);

            if (!$originalJournal) {
                return;
            }

            foreach ($originalJournal->journalEntries as $entry) {
                $entry->delete();
            }

            $assetInvoicePayment->journal_id = null;
            $assetInvoicePayment->saveQuietly();

            $originalJournal->delete();
        });
    }
} 