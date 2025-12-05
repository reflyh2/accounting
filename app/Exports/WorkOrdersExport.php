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

class WorkOrdersExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $workOrders;

    public function __construct($workOrders)
    {
        $this->workOrders = $workOrders;
    }

    public function collection()
    {
        return $this->workOrders;
    }

    public function headings(): array
    {
        return [
            '# WO',
            'BOM',
            'Produk Jadi',
            'Qty Direncanakan',
            'Qty Diterima',
            'Progress',
            'Status',
            'Tanggal Mulai',
            'Tanggal Selesai',
            'Perusahaan',
            'Cabang',
        ];
    }

    public function map($workOrder): array
    {
        return [
            $workOrder->wo_number,
            $workOrder->bom->name,
            $workOrder->bom->finished_product->name,
            $workOrder->quantity_planned,
            $workOrder->total_received_quantity,
            number_format($workOrder->progress_percentage, 2) . '%',
            $workOrder->status,
            $workOrder->scheduled_start_date ? date('d/m/Y', strtotime($workOrder->scheduled_start_date)) : '',
            $workOrder->scheduled_end_date ? date('d/m/Y', strtotime($workOrder->scheduled_end_date)) : '',
            $workOrder->branch->branchGroup->company->name,
            $workOrder->branch->name,
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
            'A1:K'.$sheet->getHighestRow() => [
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

                $event->sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                $event->sheet->getStyle('A1:K'.$event->sheet->getHighestRow())->applyFromArray([
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
                    'A' => 15, 'B' => 20, 'C' => 25, 'D' => 15, 'E' => 15,
                    'F' => 10, 'G' => 15, 'H' => 15, 'I' => 15, 'J' => 25, 'K' => 25
                ];

                foreach (range('A', 'K') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                // Add padding to cells
                $event->sheet->getStyle('A1:K'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Right-align numeric columns
                $event->sheet->getStyle('D1:E'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
