<?php

namespace App\Exports;

use App\Enums\ShippingProviderType;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShippingProvidersExport implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    protected $shippingProviders;

    public function __construct($shippingProviders)
    {
        $this->shippingProviders = $shippingProviders;
    }

    public function collection()
    {
        return $this->shippingProviders;
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Nama',
            'Tipe',
            'Kontak',
            'Telepon',
            'Email',
            'Alamat',
            'Status',
        ];
    }

    public function map($provider): array
    {
        $type = ShippingProviderType::from($provider->type)->label();

        return [
            $provider->code,
            $provider->name,
            $type,
            $provider->contact_person ?? '-',
            $provider->phone ?? '-',
            $provider->email ?? '-',
            $provider->address ?? '-',
            $provider->is_active ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setFitToWidth(1);

        return [
            1 => ['font' => ['bold' => true]],
            'A1:H'.$sheet->getHighestRow() => [
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
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 30,
            'C' => 25,
            'D' => 25,
            'E' => 20,
            'F' => 30,
            'G' => 40,
            'H' => 15,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
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

                foreach (range('A', 'H') as $column) {
                    $event->sheet->getColumnDimension($column)->setWidth($this->columnWidths()[$column]);
                }

                $event->sheet->getStyle('A1:H'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);
            },
        ];
    }
}
