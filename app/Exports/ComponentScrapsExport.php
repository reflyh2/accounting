<?php

namespace App\Exports;

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

class ComponentScrapsExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    protected $componentScraps;

    public function __construct($componentScraps)
    {
        $this->componentScraps = $componentScraps;
    }

    public function collection()
    {
        return $this->componentScraps;
    }

    public function headings(): array
    {
        return [
            'Tanggal Scrap',
            'Work Order',
            'Komponen',
            'Varian',
            'Quantity Scrap',
            'Satuan',
            'Alasan Scrap',
            'Backflush',
            'Catatan',
            'Perusahaan',
            'Cabang',
        ];
    }

    public function map($componentScrap): array
    {
        return [
            date('d/m/Y', strtotime($componentScrap->scrap_date)),
            $componentScrap->workOrder->wo_number ?? '',
            $componentScrap->componentProduct->name ?? '',
            $componentScrap->componentProductVariant ? ($componentScrap->componentProductVariant->name ?? $componentScrap->componentProductVariant->sku ?? '') : '',
            number_format($componentScrap->scrap_quantity, 3),
            $componentScrap->uom->name ?? '',
            $componentScrap->scrap_reason,
            $componentScrap->is_backflush ? 'Ya' : 'Tidak',
            $componentScrap->notes ?? '',
            $componentScrap->workOrder->branch->branchGroup->company->name ?? '',
            $componentScrap->workOrder->branch->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);

        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true]],

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
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);
                $event->sheet->getDelegate()->getPageSetup()->setFitToHeight(0);

                $event->sheet->getStyle('A1:K1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
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
                    'A' => 15, 'B' => 20, 'C' => 30, 'D' => 20, 'E' => 15,
                    'F' => 15, 'G' => 30, 'H' => 12, 'I' => 40,
                    'J' => 30, 'K' => 30,
                ];

                foreach (range('A', 'K') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column] ?? 15);
                }

                // Add padding to cells
                $event->sheet->getStyle('A1:K'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Right-align the Quantity Scrap column
                $event->sheet->getStyle('E1:E'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
