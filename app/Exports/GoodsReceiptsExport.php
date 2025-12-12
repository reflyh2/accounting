<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class GoodsReceiptsExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $goodsReceipts;

    public function __construct($goodsReceipts)
    {
        $this->goodsReceipts = $goodsReceipts;
    }

    public function collection()
    {
        return $this->goodsReceipts;
    }

    public function headings(): array
    {
        return [
            '# GRN',
            'Tanggal',
            'Supplier',
            'Lokasi',
            'Cabang',
            'Status',
            'Total Qty',
        ];
    }

    public function map($goodsReceipt): array
    {
        return [
            $goodsReceipt->receipt_number,
            date('d/m/Y', strtotime($goodsReceipt->receipt_date)),
            $goodsReceipt->supplier?->name ?? '-',
            $goodsReceipt->location?->name ?? '-',
            $goodsReceipt->branch?->name ?? '-',
            $goodsReceipt->status,
            number_format($goodsReceipt->total_quantity, 3),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $lastColumn = 'G';
                $lastRow = $event->sheet->getHighestRow();

                $event->sheet->getDelegate()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);
                $event->sheet->getDelegate()->getPageSetup()->setFitToHeight(0);
                
                $event->sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                $event->sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
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

                $columnWidths = [
                    'A' => 18, 'B' => 12, 'C' => 30, 'D' => 25, 
                    'E' => 25, 'F' => 15, 'G' => 15
                ];

                foreach ($columnWidths as $column => $width) {
                    $event->sheet->getColumnDimension($column)->setWidth($width);
                }

                $event->sheet->getStyle("G1:G{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
