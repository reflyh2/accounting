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

class GlEventConfigurationsExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    protected $configurations;

    public function __construct($configurations)
    {
        $this->configurations = $configurations;
    }

    public function collection()
    {
        $flattened = collect();

        foreach ($this->configurations as $configuration) {
            foreach ($configuration->lines as $index => $line) {
                $flattened->push((object) [
                    'configuration' => $configuration,
                    'line' => $line,
                    'is_first_line' => $index === 0,
                ]);
            }
        }

        return $flattened;
    }

    public function headings(): array
    {
        return [
            'Event Code',
            'Perusahaan',
            'Cabang',
            'Status',
            'Deskripsi',
            'Role',
            'Direction',
            'Akun',
        ];
    }

    public function map($item): array
    {
        $configuration = $item->configuration;
        $line = $item->line;

        return [
            $item->is_first_line ? $configuration->event_code : '',
            $item->is_first_line ? ($configuration->company?->name ?? '-') : '',
            $item->is_first_line ? ($configuration->branch?->name ?? '-') : '',
            $item->is_first_line ? ($configuration->is_active ? 'Aktif' : 'Tidak Aktif') : '',
            $item->is_first_line ? ($configuration->description ?? '') : '',
            $line->role,
            ucfirst($line->direction),
            $line->account->code.' - '.$line->account->name,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);
                $event->sheet->getDelegate()->getPageSetup()->setFitToHeight(0);

                $event->sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
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

                $columnWidths = [
                    'A' => 25, 'B' => 30, 'C' => 30, 'D' => 15, 'E' => 40,
                    'F' => 30, 'G' => 15, 'H' => 40,
                ];

                foreach (range('A', 'H') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($columnWidths[$column]);
                }

                $event->sheet->getStyle('A1:H'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);
            },
        ];
    }
}
