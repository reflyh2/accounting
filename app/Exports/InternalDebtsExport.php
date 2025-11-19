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

class InternalDebtsExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $debts;

    public function __construct($debts)
    {
        $this->debts = $debts;
    }

    public function collection()
    {
        return $this->debts;
    }

    public function headings(): array
    {
        return [
            '# Nomor',
            'Tgl Terbit',
            'Peminjam (Cabang)',
            'Peminjam (Perusahaan)',
            'Pemberi Pinjaman (Cabang)',
            'Pemberi Pinjaman (Perusahaan)',
            'Jatuh Tempo',
            'Mata Uang',
            'Jumlah',
            'Status',
            'Catatan',
        ];
    }

    public function map($debt): array
    {
        return [
            $debt->number,
            date('d/m/Y', strtotime($debt->issue_date)),
            $debt->branch?->name,
            $debt->branch?->branchGroup?->company?->name,
            $debt->counterpartyBranch?->name,
            $debt->counterpartyBranch?->branchGroup?->company?->name,
            $debt->due_date ? date('d/m/Y', strtotime($debt->due_date)) : '',
            $debt->currency?->code,
            number_format($debt->amount, 2),
            ucfirst(str_replace('_', ' ', $debt->status)),
            $debt->notes,
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

                $event->sheet->getStyle('I1:I'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $event->sheet->getStyle('A1:K'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);
            },
        ];
    }
}


