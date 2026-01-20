<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\AssetMaintenance;

class AssetMaintenancesExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    public function __construct(
        private readonly Collection $maintenances
    ) {
    }

    public function collection()
    {
        return $this->maintenances;
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Aset',
            'Tanggal',
            'Jenis',
            'Deskripsi',
            'Vendor',
            'Biaya Tenaga Kerja',
            'Biaya Suku Cadang',
            'Biaya Eksternal',
            'Total',
            'Status',
        ];
    }

    public function map($maintenance): array
    {
        $typeLabels = AssetMaintenance::maintenanceTypes();
        $statusLabels = AssetMaintenance::statusOptions();

        return [
            $maintenance->code,
            $maintenance->asset?->name ?? '-',
            optional($maintenance->maintenance_date)?->format('d/m/Y'),
            $typeLabels[$maintenance->maintenance_type] ?? $maintenance->maintenance_type,
            $maintenance->description,
            $maintenance->vendor?->name ?? '-',
            number_format((float) $maintenance->labor_cost, 2),
            number_format((float) $maintenance->parts_cost, 2),
            number_format((float) $maintenance->external_cost, 2),
            number_format((float) $maintenance->total_cost, 2),
            $statusLabels[$maintenance->status] ?? $maintenance->status,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);

                $sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
                ]);

                $sheet->getStyle('A1:K' . $sheet->getHighestRow())->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                    ],
                ]);

                // Right-align cost columns
                $sheet->getStyle('G2:J' . $sheet->getHighestRow())
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
