<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use App\Models\AssetMaintenanceType;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;

class AssetMaintenanceTypesExport implements FromQuery, WithHeadings, WithMapping, WithEvents
{
    use Exportable;

    protected $maintenanceTypes;

    public function __construct($maintenanceTypes = [])
    {
        $this->maintenanceTypes = $maintenanceTypes;
    }

    public function query()
    {
        if ($this->maintenanceTypes instanceof \Illuminate\Database\Eloquent\Collection) {
            return AssetMaintenanceType::whereIn('id', $this->maintenanceTypes->pluck('id'))
                ->with(['assetCategory', 'maintenanceCostAccount', 'companies'])
                ->withCount('maintenanceRecords');
        }

        return AssetMaintenanceType::query()
            ->with(['assetCategory', 'maintenanceCostAccount', 'companies'])
            ->withCount('maintenanceRecords');
    }

    public function headings(): array
    {
        return [
            'Nama Tipe Pemeliharaan',
            'Kategori Aset',
            'Deskripsi',
            'Interval Pemeliharaan',
            'Interval (Hari)',
            'Akun Biaya Pemeliharaan',
            'Perusahaan',
            'Jumlah Catatan'
        ];
    }

    public function map($maintenanceType): array
    {
        return [
            $maintenanceType->name,
            $maintenanceType->assetCategory->name ?? '-',
            $maintenanceType->description,
            $maintenanceType->maintenance_interval ?? '-',
            $maintenanceType->maintenance_interval_days ?? '-',
            $maintenanceType->maintenanceCostAccount->name ?? '-',
            $maintenanceType->companies->pluck('name')->implode(', '),
            $maintenanceType->maintenance_records_count
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);
                $event->sheet->getDelegate()->getPageSetup()->setFitToHeight(0);
                
                $event->sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                $event->sheet->getStyle('A1:H'.$event->sheet->getHighestRow())->applyFromArray([
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

                // Set column widths
                $columnWidths = [
                    'A' => 30, // Name
                    'B' => 25, // Asset Category
                    'C' => 45, // Description
                    'D' => 25, // Maintenance Interval
                    'E' => 15, // Interval Days
                    'F' => 30, // Maintenance Cost Account
                    'G' => 30, // Companies
                    'H' => 15, // Record Count
                ];

                foreach (range('A', 'H') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                // Add padding to cells
                $event->sheet->getStyle('A1:H'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Right-align numeric columns
                $event->sheet->getStyle('E2:E'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $event->sheet->getStyle('H2:H'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
} 