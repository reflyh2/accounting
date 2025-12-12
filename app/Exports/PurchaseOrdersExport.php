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

class PurchaseOrdersExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $purchaseOrders;

    public function __construct($purchaseOrders)
    {
        $this->purchaseOrders = $purchaseOrders;
    }

    public function collection()
    {
        return $this->purchaseOrders;
    }

    public function headings(): array
    {
        return [
            '# PO',
            'Tanggal',
            'Tgl Kirim',
            'Supplier',
            'Cabang',
            'Mata Uang',
            'Status',
            'Total',
        ];
    }

    public function map($purchaseOrder): array
    {
        return [
            $purchaseOrder->order_number,
            date('d/m/Y', strtotime($purchaseOrder->order_date)),
            $purchaseOrder->expected_date ? date('d/m/Y', strtotime($purchaseOrder->expected_date)) : '-',
            $purchaseOrder->partner?->name ?? '-',
            $purchaseOrder->branch?->name ?? '-',
            $purchaseOrder->currency?->code ?? '-',
            $purchaseOrder->status,
            number_format($purchaseOrder->total_amount, 2),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $lastColumn = 'H';
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
                    'A' => 18, 'B' => 12, 'C' => 12, 'D' => 30, 
                    'E' => 25, 'F' => 10, 'G' => 15, 'H' => 18
                ];

                foreach ($columnWidths as $column => $width) {
                    $event->sheet->getColumnDimension($column)->setWidth($width);
                }

                $event->sheet->getStyle("H1:H{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
