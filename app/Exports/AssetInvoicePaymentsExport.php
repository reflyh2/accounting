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
use App\Models\AssetInvoicePayment;

class AssetInvoicePaymentsExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $payments;

    public function __construct($payments)
    {
        $this->payments = $payments;
    }

    public function collection()
    {
        return $this->payments;
    }

    public function headings(): array
    {
        return [
            '# Pembayaran',
            'Tanggal Bayar',
            'Tipe',
            'Partner',
            'Referensi',
            'Jumlah',
            'Metode Bayar',
            'Catatan',
        ];
    }

    public function map($payment): array
    {
        $paymentMethods = AssetInvoicePayment::getPaymentMethods();
        $paymentTypes = AssetInvoicePayment::getTypes();

        return [
            $payment->number,
            date('d/m/Y', strtotime($payment->payment_date)),
            $paymentTypes[$payment->type] ?? $payment->type,
            $payment->partner->name,
            $payment->reference,
            number_format($payment->amount, 2),
            $paymentMethods[$payment->payment_method] ?? $payment->payment_method,
            $payment->notes,
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

                // Set column widths
                $columnWidths = [
                    'A' => 20, 'B' => 15, 'C' => 20, 'D' => 25, 'E' => 20,
                    'F' => 15, 'G' => 15, 'H' => 40
                ];

                foreach (range('A', 'H') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                // Add padding to cells
                $event->sheet->getStyle('A1:H'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                // Right-align the amount column
                $event->sheet->getStyle('F1:F'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
} 