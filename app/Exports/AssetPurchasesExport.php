<?php

namespace App\Exports;

use App\Models\AssetInvoice;
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

class AssetPurchasesExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $assetPurchases;

    public function __construct($assetPurchases)
    {
        $this->assetPurchases = $assetPurchases;
    }

    public function collection()
    {
        return $this->assetPurchases;
    }

    public function headings(): array
    {
        // Define column headers for the export file
        return [
            '# Faktur',
            'Tgl Faktur',
            'Partner',
            'Jatuh Tempo',
            'Status',
            'Catatan',
            'Perusahaan',
            'Cabang',
            'Total',
        ];
    }

    public function map($invoice): array
    {
        // Map data from each AssetInvoice model to an array for the row
        return [
            $invoice->number, // Invoice number
            date('d/m/Y', strtotime($invoice->invoice_date)), // Formatted invoice date
            $invoice->partner->name, // Partner name
            date('d/m/Y', strtotime($invoice->due_date)), // Formatted due date
            ucfirst(str_replace('_', ' ', $invoice->status)), // Status (formatted)
            $invoice->notes, // Notes
            $invoice->branch->branchGroup->company->name, // Company name
            $invoice->branch->name, // Branch name
            number_format($invoice->total_amount, 2), // Total amount (formatted)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Set basic page setup
                $event->sheet->getDelegate()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);
                $event->sheet->getDelegate()->getPageSetup()->setFitToHeight(0);

                // Style header row
                $event->sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                // Apply borders and alignment to all data
                $event->sheet->getStyle('A1:I'.$event->sheet->getHighestRow())->applyFromArray([
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

                // Right-align the Total column (Column I)
                $event->sheet->getStyle('I1:I'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Add padding (indent) to all cells
                $event->sheet->getStyle('A1:I'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Optionally set specific column widths if ShouldAutoSize is not sufficient
                 $columnWidths = [
                     'A' => 20, 'B' => 15, 'C' => 30, 'D' => 15, 'E' => 15, 
                     'F' => 40, 'G' => 30, 'H' => 30, 'I' => 20
                 ];
                 foreach (range('A', 'I') as $column) {
                     $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                 }
            },
        ];
    }
} 