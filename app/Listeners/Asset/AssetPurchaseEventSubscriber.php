<?php

namespace App\Listeners\Asset;

use App\Events\Asset\AssetPurchaseCreated;
use App\Events\Asset\AssetPurchaseDeleted;
use App\Events\Asset\AssetPurchaseUpdated;
use App\Models\Journal;
use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Models\AssetInvoice;
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
        $this->generateDepreciationSchedules($event->assetInvoice);
    }

    public function handleAssetPurchaseUpdated(AssetPurchaseUpdated $event): void
    {
        $this->deleteJournal($event->assetInvoice);
        $this->createJournalForAssetPurchase($event->assetInvoice);
        $this->regenerateDepreciationSchedules($event->assetInvoice);
    }

    public function handleAssetPurchaseDeleted(AssetPurchaseDeleted $event): void
    {
        $this->deleteJournal($event->assetInvoice);
        $this->deleteSchedulesForInvoice($event->assetInvoice);
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

    private function generateDepreciationSchedules($assetInvoice): void
    {
        // Generate depreciation/amortization schedules for each asset on the invoice
        $assetInvoice->loadMissing(['assetInvoiceDetails.asset.category', 'branch.branchGroup.company']);
        $company = $assetInvoice->branch->branchGroup->company;

        DB::transaction(function () use ($assetInvoice, $company) {
            foreach ($assetInvoice->assetInvoiceDetails as $detail) {
                /** @var Asset $asset */
                $asset = $detail->asset;
                if (!$asset) {
                    continue;
                }

                // Only for depreciable/amortizable assets with a start date and useful life
                if ((!$asset->is_depreciable && !$asset->is_amortizable) || !$asset->depreciation_start_date || !$asset->useful_life_months || $asset->useful_life_months <= 0) {
                    continue;
                }

                // Clear existing future schedules if any (idempotent generation in case of re-run on create)
                AssetDepreciationSchedule::where('asset_id', $asset->id)->delete();

                $amounts = $this->buildDepreciationAmountsForAsset($asset, (float) ($detail->line_amount ?? $asset->cost_basis));
                $months = count($amounts);
                $firstDate = $asset->depreciation_start_date->copy();
                $dayOfMonth = (int) $firstDate->day;

                for ($i = 0; $i < $months; $i++) {
                    $date = $firstDate->copy();
                    $date->day = 1; // Set to first of month to avoid skipping months
                    $date->addMonths($i); // Add months after setting to first
                    $date->day = min($dayOfMonth, $date->daysInMonth); // Then set the target day

                    AssetDepreciationSchedule::create([
                        'asset_id' => $asset->id,
                        'sequence_number' => $i + 1,
                        'schedule_date' => $date->toDateString(),
                        'amount' => $amounts[$i],
                        'method' => $asset->depreciation_method,
                        'is_processed' => false,
                    ]);
                }
            }
        });
    }

    private function regenerateDepreciationSchedules($assetInvoice): void
    {
        $assetInvoice->loadMissing(['assetInvoiceDetails.asset.category', 'branch.branchGroup.company']);

        DB::transaction(function () use ($assetInvoice) {
            foreach ($invoice->assetInvoiceDetails as $detail) {
                /** @var Asset $asset */
                $asset = $detail->asset;
                if (!$asset) {
                    continue;
                }

                if ((!$asset->is_depreciable && !$asset->is_amortizable) || !$asset->depreciation_start_date || !$asset->useful_life_months || $asset->useful_life_months <= 0) {
                    continue;
                }

                $existing = AssetDepreciationSchedule::where('asset_id', $asset->id)->orderBy('sequence_number')->get();
                $processedCount = $existing->where('is_processed', true)->count();

                // Remove all unprocessed schedules
                AssetDepreciationSchedule::where('asset_id', $asset->id)->where('is_processed', false)->delete();

                $amountsAll = $this->buildDepreciationAmountsForAsset($asset, (float) ($detail->line_amount ?? $asset->cost_basis));
                $remainingAmounts = array_slice($amountsAll, $processedCount);

                if (empty($remainingAmounts)) {
                    continue;
                }

                // Determine next sequence number and date
                $nextSeq = $processedCount + 1;
                $firstDate = $asset->depreciation_start_date->copy()->addMonths($processedCount);
                $dayOfMonth = (int) $asset->depreciation_start_date->day;

                foreach ($remainingAmounts as $idx => $amt) {
                    $date = $firstDate->copy();
                    $date->day = 1; // Set to first of month to avoid skipping months
                    $date->addMonths($i); // Add months after setting to first
                    $date->day = min($dayOfMonth, $date->daysInMonth); // Then set the target day

                    AssetDepreciationSchedule::create([
                        'asset_id' => $asset->id,
                        'sequence_number' => $nextSeq + $idx,
                        'schedule_date' => $date->toDateString(),
                        'amount' => $amt,
                        'method' => $asset->depreciation_method,
                        'is_processed' => false,
                    ]);
                }
            }
        });
    }

    private function deleteSchedulesForInvoice($assetInvoice): void
    {
        foreach ($assetInvoice->assetInvoiceDetails as $detail) {
            $assetId = $detail->asset?->id;
            if (!$assetId) {
                continue;
            }
            AssetDepreciationSchedule::where('asset_id', $assetId)->where('is_processed', false)->delete();
        }
    }

    private function buildDepreciationAmountsForAsset(Asset $asset, float $fallbackCostBasis = 0.0): array
    {
        $cost = (float) ($asset->cost_basis ?? $fallbackCostBasis);
        $salvage = (float) ($asset->salvage_value ?? 0);
        $months = (int) ($asset->useful_life_months ?? 0);
        $method = (string) $asset->depreciation_method;

        if ($months <= 0 || $cost <= 0) {
            return [];
        }

        switch ($method) {
            case 'straight-line':
                return $this->calculateStraightLine($cost, $salvage, $months);
            case 'declining-balance':
                return $this->calculateDecliningBalance($cost, $salvage, $months);
            case 'sum-of-years-digits':
                return $this->calculateSumOfYearsDigits($cost, $salvage, $months);
            case 'units-of-production':
                return $this->calculateUnitsOfProduction($cost, $salvage, $months);
            case 'no-depreciation':
            default:
                return $this->calculateNoDepreciation($months);
        }
    }

    private function calculateStraightLine(float $cost, float $salvage, int $months): array
    {
        $base = max(0, $cost - $salvage);
        if ($months <= 0 || $base <= 0) return array_fill(0, max(0, $months), 0.0);
        $per = round($base / $months, 2);
        $amounts = array_fill(0, $months, $per);
        // Adjust last for rounding
        $amounts[$months - 1] = round($base - $per * ($months - 1), 2);
        return $amounts;
    }

    private function calculateDecliningBalance(float $cost, float $salvage, int $months): array
    {
        $base = max(0, $cost - $salvage);
        if ($months <= 0 || $base <= 0) return array_fill(0, max(0, $months), 0.0);
        // Double-declining balance using monthly rate derived from life months: rate = 2 / life_years / 12 -> 2 / life_months
        $rate = 2 / $months; // monthly rate approximation
        $bookValue = $cost;
        $amounts = [];
        for ($i = 0; $i < $months; $i++) {
            $depr = round($bookValue * $rate, 2);
            // Prevent going below salvage
            $maxAllowed = max(0, ($bookValue - $depr) - $salvage);
            if ($maxAllowed < 0) {
                $depr = round($bookValue - $salvage, 2);
            }
            // Ensure final month adjusts residue
            if ($i === $months - 1) {
                $already = array_sum($amounts);
                $depr = round($base - $already, 2);
            }
            $amounts[] = max(0, $depr);
            $bookValue = max($salvage, $bookValue - $depr);
        }
        return $amounts;
    }

    private function calculateSumOfYearsDigits(float $cost, float $salvage, int $months): array
    {
        $base = max(0, $cost - $salvage);
        if ($months <= 0 || $base <= 0) return array_fill(0, max(0, $months), 0.0);
        $sum = ($months * ($months + 1)) / 2;
        $amounts = [];
        for ($i = 0; $i < $months; $i++) {
            // First month gets highest fraction: months-i
            $fraction = ($months - $i) / $sum;
            $amounts[] = round($base * $fraction, 2);
        }
        // Adjust last for rounding
        $amounts[$months - 1] = round($base - array_sum(array_slice($amounts, 0, $months - 1)), 2);
        return $amounts;
    }

    private function calculateUnitsOfProduction(float $cost, float $salvage, int $months): array
    {
        // Lacking unit forecasts; default to straight-line as pragmatic fallback
        return $this->calculateStraightLine($cost, $salvage, $months);
    }

    private function calculateNoDepreciation(int $months): array
    {
        return array_fill(0, max(0, $months), 0.0);
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