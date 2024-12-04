<?php

namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class PartnersExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder, 
    ShouldAutoSize, 
    WithEvents
{
    protected $partners;

    public function __construct($partners)
    {
        $this->partners = $partners;
    }

    public function collection()
    {
        return $this->partners;
    }

    public function headings(): array
    {
        return [
            'Nama',
            'Email',
            'Telepon',
            'Alamat',
            'NPWP',
            'Nomor Pendaftaran',
            'Industri',
            'Website',
            'Status',
            'Perusahaan',
            'Limit Kredit',
            'Term Pembayaran',
            'Tag'
        ];
    }

    public function map($partner): array
    {
        return [
            $partner->name,
            $partner->email,
            $partner->phone,
            $partner->address,
            $partner->tax_id,
            $partner->registration_number,
            $partner->industry,
            $partner->website,
            $partner->status,
            $partner->companies->pluck('name')->join(', '),
            $partner->creditTerms->credit_limit ?? 0,
            $partner->creditTerms ? 
                ($partner->creditTerms->payment_term_type . ' ' . $partner->creditTerms->payment_term_days . ' days') : 
                'N/A',
            $partner->tags->pluck('tag_name')->implode(', ')
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getStyle('A1:M1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0']
                    ]
                ]);

                $event->sheet->getStyle('A1:M'.$event->sheet->getHighestRow())->applyFromArray([
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

                $event->sheet->getStyle('A1:M'.$event->sheet->getHighestRow())
                    ->getAlignment()->setIndent(1);
            },
        ];
    }
} 