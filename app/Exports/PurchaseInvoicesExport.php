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

class PurchaseInvoicesExport implements
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
            'Supplier',
            'Nomor PO',
            'Status',
            'Mata Uang',
            'Nilai Faktur',
            'PPV',
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            optional($invoice->invoice_date)->format('d/m/Y'),
            $invoice->partner?->name,
            $invoice->purchaseOrder?->order_number,
            $invoice->status,
            $invoice->currency?->code,
            number_format((float) $invoice->total_amount, 2),
            number_format((float) $invoice->ppv_amount, 2),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A1:H{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => '000000'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("G2:H{$highestRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}

