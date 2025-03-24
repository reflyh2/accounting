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

class AssetsExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $assets;

    public function __construct($assets)
    {
        $this->assets = $assets;
    }

    public function collection()
    {
        return $this->assets;
    }

    public function headings(): array
    {
        return [
            'Nama Aset',
            'Kategori',
            'Tipe',
            'Jenis Pengadaan',
            'Serial Number',
            'Status',
            'Harga Beli',
            'Nilai Sekarang',
            'Tanggal Pembelian',
            'Supplier',
            'Lokasi',
            'Departemen',
            'Masa Garansi',
            'Metode Penyusutan',
            'Usia Ekonomis (Bulan)',
            'Nilai Sisa',
            'Tanggal Revaluasi',
            'Jumlah Revaluasi',
            'Status Penurunan Nilai',
            'Jumlah Penurunan Nilai',
            'Tanggal Penurunan Nilai',
        ];
    }

    public function map($asset): array
    {
        return [
            $asset->name,
            $asset->category->name,
            ucfirst($asset->asset_type),
            ucfirst($asset->acquisition_type),
            $asset->serial_number,
            ucfirst($asset->status),
            number_format($asset->purchase_cost, 2),
            number_format($asset->current_value ?? $asset->calculateDepreciation(), 2),
            date('d/m/Y', strtotime($asset->purchase_date)),
            $asset->supplier,
            $asset->location,
            $asset->department,
            $asset->warranty_expiry ? date('d/m/Y', strtotime($asset->warranty_expiry)) : '-',
            ucfirst(str_replace('-', ' ', $asset->depreciation_method)),
            $asset->useful_life_months,
            number_format($asset->salvage_value, 2),
            $asset->last_revaluation_date ? date('d/m/Y', strtotime($asset->last_revaluation_date)) : '-',
            $asset->last_revaluation_amount ? number_format($asset->last_revaluation_amount, 2) : '-',
            $asset->is_impaired ? 'Yes' : 'No',
            $asset->impairment_amount ? number_format($asset->impairment_amount, 2) : '-',
            $asset->impairment_date ? date('d/m/Y', strtotime($asset->impairment_date)) : '-',
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
                
                $event->sheet->getStyle('A1:U1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                $event->sheet->getStyle('A1:U'.$event->sheet->getHighestRow())->applyFromArray([
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
                    'B' => 20, // Category
                    'C' => 15, // Type
                    'D' => 20, // Acquisition Type
                    'E' => 20, // Serial Number
                    'F' => 15, // Status
                    'G' => 15, // Purchase Cost
                    'H' => 15, // Current Value
                    'I' => 15, // Purchase Date
                    'J' => 25, // Supplier
                    'K' => 20, // Location
                    'L' => 20, // Department
                    'M' => 15, // Warranty Expiry
                    'N' => 20, // Depreciation Method
                    'O' => 15, // Useful Life
                    'P' => 15, // Salvage Value
                    'Q' => 20, // Last Revaluation Date
                    'R' => 20, // Last Revaluation Amount
                    'S' => 15, // Impairment Status
                    'T' => 15, // Impairment Amount
                    'U' => 15, // Impairment Date
                ];

                foreach (range('A', 'U') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                // Add padding to cells
                $event->sheet->getStyle('A1:U'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Right-align the numeric columns
                $numericColumns = ['G', 'H', 'O', 'P', 'R', 'T'];
                foreach ($numericColumns as $column) {
                    $event->sheet->getStyle($column.'2:'.$column.$event->sheet->getHighestRow())
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }
} 