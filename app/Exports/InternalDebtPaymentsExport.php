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

class InternalDebtPaymentsExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $items;
    protected string $title;

    public function __construct($items, string $title = 'Pembayaran/Penerimaan Internal')
    {
        $this->items = $items;
        $this->title = $title;
    }

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            '# Dokumen',
            'Tanggal',
            'Perusahaan',
            'Cabang',
            'Mata Uang',
            'Jumlah',
            'Metode',
            'Referensi',
            'Status',
            'Catatan',
            '# Hutang/Piutang',
        ];
    }

    public function map($item): array
    {
        $debtNumbers = method_exists($item, 'details') ? $item->details->map(fn($d) => $d->internalDebt?->number)->filter()->implode(', ') : '';
        return [
            $item->number,
            date('d/m/Y', strtotime($item->payment_date)),
            $item->branch?->branchGroup?->company?->name,
            $item->branch?->name,
            $item->currency?->code,
            number_format($item->amount, 2),
            $item->payment_method,
            $item->reference_number,
            ucfirst($item->status ?? ''),
            $item->notes,
            $debtNumbers,
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
                $event->sheet->getStyle('F1:F'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $columnWidths = [
                    'A' => 22, 'B' => 15, 'C' => 28, 'D' => 24, 'E' => 12,
                    'F' => 18, 'G' => 16, 'H' => 20, 'I' => 14, 'J' => 36, 'K' => 24
                ];
                foreach ($columnWidths as $col => $width) {
                    $event->sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}


