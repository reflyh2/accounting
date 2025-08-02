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

class AssetTransfersExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $assetTransfers;

    public function __construct($assetTransfers)
    {
        $this->assetTransfers = $assetTransfers;
    }

    public function collection()
    {
        return $this->assetTransfers;
    }

    public function headings(): array
    {
        return [
            '# Transfer',
            'Tgl Transfer',
            'Dari Perusahaan',
            'Dari Cabang',
            'Ke Perusahaan',
            'Ke Cabang',
            'Status',
            'Catatan',
        ];
    }

    public function map($transfer): array
    {
        return [
            $transfer->number,
            date('d/m/Y', strtotime($transfer->transfer_date)),
            $transfer->fromCompany->name,
            $transfer->fromBranch->name,
            $transfer->toCompany->name,
            $transfer->toBranch->name,
            ucfirst(str_replace('_', ' ', $transfer->status)),
            $transfer->notes,
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
                
                $event->sheet->getStyle('A1:H'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                 $columnWidths = [
                     'A' => 20, 'B' => 15, 'C' => 30, 'D' => 30, 'E' => 30, 
                     'F' => 30, 'G' => 15, 'H' => 40
                 ];
                 foreach (range('A', 'H') as $column) {
                     $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                 }
            },
        ];
    }
} 