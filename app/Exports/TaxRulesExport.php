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

class TaxRulesExport extends DefaultValueBinder implements 
    FromCollection, 
    WithHeadings, 
    WithMapping, 
    WithCustomValueBinder,
    WithEvents,
    ShouldAutoSize
{
    protected $rules;

    public function __construct($rules)
    {
        $this->rules = $rules;
    }

    public function collection()
    {
        return $this->rules;
    }

    public function headings(): array
    {
        return [
            'Kategori Pajak',
            'Yurisdiksi',
            'Komponen',
            'Tipe Tarif',
            'Tarif',
            'Termasuk Pajak',
            'Berlaku B2B',
            'Reverse Charge',
            'Berlaku Dari',
            'Berlaku Sampai',
            'Prioritas',
        ];
    }

    public function map($rule): array
    {
        return [
            $rule->taxCategory?->name,
            $rule->jurisdiction?->name,
            $rule->component?->name,
            $rule->rate_type === 'percent' ? 'Persentase' : 'Tetap Per Unit',
            $rule->rate_type === 'percent' ? ($rule->rate_value . '%') : $rule->rate_value,
            $rule->tax_inclusive ? 'Ya' : 'Tidak',
            $rule->b2b_applicable === null ? '-' : ($rule->b2b_applicable ? 'Ya' : 'Tidak'),
            $rule->reverse_charge ? 'Ya' : 'Tidak',
            $rule->effective_from?->format('d/m/Y'),
            $rule->effective_to?->format('d/m/Y') ?? '-',
            $rule->priority,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
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
            },
        ];
    }
}
