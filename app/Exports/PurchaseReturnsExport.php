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

class PurchaseReturnsExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    public function __construct(
        private readonly $purchaseReturns
    ) {
    }

    public function collection()
    {
        return $this->purchaseReturns;
    }

    public function headings(): array
    {
        return [
            '# Retur',
            'Tanggal',
            'Supplier',
            'Purchase Order',
            'Goods Receipt',
            'Alasan',
            'Total (Doc)',
            'Total (Base)',
        ];
    }

    public function map($purchaseReturn): array
    {
        return [
            $purchaseReturn->return_number,
            optional($purchaseReturn->return_date)->format('d/m/Y'),
            $purchaseReturn->partner->name ?? '-',
            $purchaseReturn->purchaseOrder->order_number ?? '-',
            $purchaseReturn->goodsReceipt->receipt_number ?? '-',
            $purchaseReturn->reason_code ? (config('purchasing.return_reasons')[$purchaseReturn->reason_code] ?? $purchaseReturn->reason_code) : '-',
            number_format((float) $purchaseReturn->total_value, 2),
            number_format((float) $purchaseReturn->total_value_base, 2),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);

                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
                ]);

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

                $sheet->getStyle('G2:H' . $sheet->getHighestRow())
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
}


