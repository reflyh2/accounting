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
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;

class AssetCategoriesExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = AssetCategory::query()
            ->with([
                'companies',
                'fixedAssetAccount',
                'purchasePayableAccount',
                'accumulatedDepreciationAccount',
                'depreciationExpenseAccount',
                'prepaidRentAccount',
                'rentExpenseAccount'
            ])
            ->withCount('assets');

        if (!empty($this->filters['search'])) {
            $query->where('name', 'like', '%' . $this->filters['search'] . '%');
        }

        return $query;
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
            $category->description,
            $category->companies->pluck('name')->implode(', '),
            $category->fixedAssetAccount?->name ?? '-',
            $category->purchasePayableAccount?->name ?? '-',
            $category->accumulatedDepreciationAccount?->name ?? '-',
            $category->depreciationExpenseAccount?->name ?? '-',
            $category->prepaidRentAccount?->name ?? '-',
            $category->rentExpenseAccount?->name ?? '-',
            $category->assets_count
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
                    'A' => 30, // Name
                    'B' => 55, // Description
                    'C' => 15, // Company
                    'D' => 15, // Fixed Asset Account
                    'E' => 15, // Purchase Payable Account
                    'F' => 15, // Accumulated Depreciation Account
                    'G' => 15, // Depreciation Expense Account
                    'H' => 15, // Prepaid Rent Account
                    'I' => 15, // Rent Expense Account
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