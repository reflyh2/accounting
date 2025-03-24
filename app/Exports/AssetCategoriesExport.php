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
use App\Models\AssetCategory;

class AssetCategoriesExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $categories;

    public function __construct($categories)
    {
        $this->categories = $categories;
    }

    public function collection()
    {
        return $this->categories;
    }

    public function headings(): array
    {
        return [
            'Nama Kategori',
            'Deskripsi',
            'Perusahaan',
            'Akun Aset Tetap',
            'Akun Hutang Pembelian',
            'Akun Akumulasi Penyusutan',
            'Akun Beban Penyusutan',
            'Akun Sewa Dibayar Dimuka',
            'Akun Beban Sewa',
            'Jumlah Aset'
        ];
    }

    public function map($category): array
    {
        return [
            $category->name,
            $category->description ?? '-',
            $category->companies->pluck('name')->implode(', '),
            $category->fixedAssetAccount ? $category->fixedAssetAccount->code . ' - ' . $category->fixedAssetAccount->name : '-',
            $category->purchasePayableAccount ? $category->purchasePayableAccount->code . ' - ' . $category->purchasePayableAccount->name : '-',
            $category->accumulatedDepreciationAccount ? $category->accumulatedDepreciationAccount->code . ' - ' . $category->accumulatedDepreciationAccount->name : '-',
            $category->depreciationExpenseAccount ? $category->depreciationExpenseAccount->code . ' - ' . $category->depreciationExpenseAccount->name : '-',
            $category->prepaidRentAccount ? $category->prepaidRentAccount->code . ' - ' . $category->prepaidRentAccount->name : '-',
            $category->rentExpenseAccount ? $category->rentExpenseAccount->code . ' - ' . $category->rentExpenseAccount->name : '-',
            $category->assets_count
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);

        return [
            // Style the first row as bold text
            1    => ['font' => ['bold' => true]],

            // Styling the entire sheet
            'A1:J'.$sheet->getHighestRow() => [
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
            ],
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
                
                $event->sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                $event->sheet->getStyle('A1:J'.$event->sheet->getHighestRow())->applyFromArray([
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
                    'A' => 25, // Name
                    'B' => 35, // Description
                    'C' => 30, // Company
                    'D' => 25, // Fixed Asset Account
                    'E' => 25, // Purchase Payable Account
                    'F' => 25, // Accumulated Depreciation Account
                    'G' => 25, // Depreciation Expense Account
                    'H' => 25, // Prepaid Rent Account
                    'I' => 25, // Rent Expense Account
                    'J' => 15, // Asset Count
                ];

                foreach (range('A', 'J') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                // Add padding to cells
                $event->sheet->getStyle('A1:J'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Right-align the asset count column
                $event->sheet->getStyle('J2:J'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
} 