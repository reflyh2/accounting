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

class AssetFinancingAgreementsExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $agreements;

    public function __construct($agreements)
    {
        $this->agreements = $agreements;
    }

    public function collection()
    {
        return $this->agreements;
    }

    public function headings(): array
    {
        return [
            'Nomor Perjanjian',
            'Tanggal Perjanjian',
            'Kreditor',
            'Invoice Aset',
            'Total Jumlah',
            'Suku Bunga (%)',
            'Tanggal Mulai',
            'Tanggal Selesai',
            'Frekuensi Pembayaran',
            'Status',
            'Catatan',
        ];
    }

    public function map($agreement): array
    {
        return [
            $agreement->number,
            date('d/m/Y', strtotime($agreement->agreement_date)),
            $agreement->creditor->name ?? '',
            $agreement->assetInvoice->invoice_number ?? '',
            number_format($agreement->total_amount, 2),
            number_format($agreement->interest_rate, 2),
            date('d/m/Y', strtotime($agreement->start_date)),
            date('d/m/Y', strtotime($agreement->end_date)),
            $agreement->payment_frequency_label,
            $agreement->status_label,
            $agreement->notes ?? '',
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
                    'A' => 20, 'B' => 15, 'C' => 25, 'D' => 20, 'E' => 15,
                    'F' => 15, 'G' => 15, 'H' => 15, 'I' => 20, 'J' => 15, 'K' => 30
                ];

                foreach (range('A', 'K') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                // Add padding to cells
                $event->sheet->getStyle('A1:K'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Right-align the Total Amount and Interest Rate columns
                $event->sheet->getStyle('E1:F'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
} 