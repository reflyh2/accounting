<?php

namespace App\Exports;

use App\Models\AssetDisposal;
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

class AssetDisposalsExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $assetDisposals;

    public function __construct($assetDisposals)
    {
        $this->assetDisposals = $assetDisposals;
    }

    public function collection()
    {
        return $this->assetDisposals;
    }

    public function headings(): array
    {
        return [
            '# Dokumen',
            'Tgl Pelepasan',
            'Jenis',
            'Status',
            'Perusahaan',
            'Cabang',
            'Hasil',
            'Catatan',
        ];
    }

    public function map($disposal): array
    {
        return [
            $disposal->number,
            date('d/m/Y', strtotime($disposal->disposal_date)),
            AssetDisposal::disposalTypeOptions()[$disposal->disposal_type] ?? $disposal->disposal_type,
            AssetDisposal::statusOptions()[$disposal->status] ?? $disposal->status,
            $disposal->branch->branchGroup->company->name,
            $disposal->branch->name,
            number_format($disposal->proceeds_amount, 2),
            $disposal->notes,
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
                
                $event->sheet->getStyle('G1:G'.$event->sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $event->sheet->getStyle('A1:H'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);
            },
        ];
    }
} 