<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SalesInvoicesExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithEvents,
    ShouldAutoSize
{
    public function __construct(
        private readonly Collection $invoices,
    ) {
    }

    public function collection(): Collection
    {
        return $this->invoices;
    }

    public function headings(): array
    {
        return [
            'Nomor Faktur',
            'Tanggal',
            'Customer',
            'Nomor SO',
            'Status',
            'Mata Uang',
            'Nilai Faktur',
            'Revenue Variance',
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            optional($invoice->invoice_date)->format('d/m/Y'),
            $invoice->partner?->name,
            $invoice->salesOrder?->order_number,
            $invoice->status,
            $invoice->currency?->code,
            number_format($invoice->total_amount, 2),
            number_format($invoice->revenue_variance, 2),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Set column widths
                $columnWidths = [
                    'A' => 20, 'B' => 12, 'C' => 25, 'D' => 15,
                    'E' => 12, 'F' => 10, 'G' => 15, 'H' => 15
                ];

                foreach (range('A', 'H') as $column) {
                    $sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                // Style the header row
                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                // Style all cells
                $sheet->getStyle('A1:H' . $sheet->getHighestRow())->applyFromArray([
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

                // Right-align numeric columns
                $sheet->getStyle('G1:H' . $sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}
