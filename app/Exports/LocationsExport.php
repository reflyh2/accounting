<?php

namespace App\Exports;

use App\Models\Location;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LocationsExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping
{
    protected $locations;

    public function __construct($locations)
    {
        $this->locations = $locations;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->locations;
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Nama Lokasi',
            'Tipe',
            'Cabang',
            'Status',
        ];
    }

    /**
     * @param  Location  $location
     */
    public function map($location): array
    {
        return [
            $location->code,
            $location->name,
            $location->type,
            $location->branch->name,
            $location->is_active ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 30,
            'C' => 20,
            'D' => 30,
            'E' => 15,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);
                $event->sheet->getDelegate()->getPageSetup()->setFitToHeight(0);

                $event->sheet->getStyle('A1:E1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
                ]);

                $event->sheet->getStyle('A1:E'.$event->sheet->getHighestRow())->applyFromArray([
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

                foreach (range('A', 'E') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($this->columnWidths()[$column]);
                }

                $event->sheet->getStyle('A1:E'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);
            },
        ];
    }
}
