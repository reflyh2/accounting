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

class ExternalDebtPaymentsExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $items;
    protected string $title;

    public function __construct($items, string $title = 'Pembayaran/Penerimaan')
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
            'Partner',
            'Perusahaan',
            'Cabang',
            'Mata Uang',
            'Jumlah',
            'Metode',
            'Referensi',
            'Catatan',
            '# Hutang/Piutang',
        ];
    }

    public function map($item): array
    {
        $debtNumbers = method_exists($item, 'details') ? $item->details->map(fn($d) => $d->externalDebt?->number)->filter()->implode(', ') : '';
        return [
            $item->number,
            date('d/m/Y', strtotime($item->payment_date)),
            $item->partner?->name,
            $item->branch?->branchGroup?->company?->name,
            $item->branch?->name,
            $item->currency?->code,
            number_format($item->amount, 2),
            $item->payment_method,
            $item->reference_number,
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

                $event->sheet->getStyle('G1:G'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $event->sheet->getStyle('A1:K'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);

                $columnWidths = [
                    'A' => 20, 'B' => 15, 'C' => 30, 'D' => 30, 'E' => 20,
                    'F' => 10, 'G' => 18, 'H' => 18, 'I' => 18, 'J' => 40, 'K' => 20
                ];
                foreach (range('A', 'K') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }
            },
        ];
    }
}


