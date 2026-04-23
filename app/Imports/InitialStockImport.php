<?php

namespace App\Imports;

use App\Exceptions\InventoryException;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Services\Inventory\DTO\AdjustDTO;
use App\Services\Inventory\DTO\AdjustLineDTO;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk-import initial stock as one inventory adjustment document per location.
 * All quantities are treated as positive stock-in; unit_cost required per row.
 */
class InitialStockImport implements ToCollection, WithHeadingRow
{
    /** @var string[] */
    public array $errors = [];

    public int $documentsCreated = 0;

    public int $linesCreated = 0;

    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'Berkas kosong atau tidak memiliki baris data.';

            return;
        }

        $variantsBySku = ProductVariant::query()->whereNotNull('sku')->with('uom')->get()->keyBy('sku');
        $locationsByCode = Location::query()->get()->keyBy('code');

        $grouped = [];
        foreach ($rows as $index => $row) {
            $lineNo = $index + 2;
            $sku = trim((string) ($row['sku'] ?? ''));
            $locationCode = trim((string) ($row['lokasi_kode'] ?? ''));

            if ($sku === '' && $locationCode === '') {
                continue;
            }

            if ($sku === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'sku': wajib diisi.";

                continue;
            }
            $variant = $variantsBySku->get($sku);
            if (! $variant) {
                $this->errors[] = "Baris {$lineNo}, kolom 'sku': '{$sku}' tidak ditemukan.";

                continue;
            }
            if (! $variant->uom_id) {
                $this->errors[] = "Baris {$lineNo}, kolom 'sku': '{$sku}' tidak memiliki satuan.";

                continue;
            }

            if ($locationCode === '') {
                $this->errors[] = "Baris {$lineNo}, kolom 'lokasi_kode': wajib diisi.";

                continue;
            }
            $location = $locationsByCode->get($locationCode);
            if (! $location) {
                $this->errors[] = "Baris {$lineNo}, kolom 'lokasi_kode': '{$locationCode}' tidak ditemukan.";

                continue;
            }

            $quantity = $row['jumlah'] ?? null;
            if (! is_numeric($quantity) || (float) $quantity <= 0) {
                $this->errors[] = "Baris {$lineNo}, kolom 'jumlah': harus berupa angka positif.";

                continue;
            }

            $unitCost = $row['harga_satuan'] ?? null;
            if (! is_numeric($unitCost) || (float) $unitCost < 0) {
                $this->errors[] = "Baris {$lineNo}, kolom 'harga_satuan': harus berupa angka (>= 0).";

                continue;
            }

            $grouped[$location->id][] = [
                'line' => $lineNo,
                'variant_id' => $variant->id,
                'uom_id' => $variant->uom_id,
                'quantity' => (float) $quantity,
                'unit_cost' => (float) $unitCost,
            ];
        }

        if (! empty($this->errors)) {
            return;
        }

        DB::transaction(function () use ($grouped) {
            $today = CarbonImmutable::now();
            foreach ($grouped as $locationId => $lines) {
                $lineDtos = array_map(
                    fn ($line) => new AdjustLineDTO(
                        $line['variant_id'],
                        $line['uom_id'],
                        $line['quantity'],
                        $line['unit_cost'],
                    ),
                    $lines,
                );

                $dto = new AdjustDTO(
                    $today,
                    (int) $locationId,
                    $lineDtos,
                    'Saldo awal (impor)',
                    'Diimpor dari berkas saldo awal.',
                );

                try {
                    $this->inventoryService->adjust($dto);
                } catch (InventoryException $e) {
                    foreach ($lines as $line) {
                        $this->errors[] = "Baris {$line['line']}: gagal saat memposting ({$e->getMessage()}).";
                    }
                    throw new ImportRollbackException;
                }

                $this->documentsCreated++;
                $this->linesCreated += count($lines);
            }
        });
    }
}
