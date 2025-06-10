<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AssetSalesExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithMapping
{
    protected $sales;

    public function __construct($sales)
    {
        $this->sales = $sales;
    }

    public function collection()
    {
        return $this->sales;
    }

    public function headings(): array
    {
        return [
            'Nomor Faktur',
            'Tanggal Faktur',
            'Jatuh Tempo',
            'Perusahaan',
            'Cabang',
            'Customer',
            'Mata Uang',
            'Nilai Tukar',
            'Total Faktur',
            'Status',
            'Catatan',
            'Dibuat Oleh',
            'Tanggal Dibuat',
        ];
    }

    public function map($sale): array
    {
        return [
            $sale->number,
            $sale->invoice_date,
            $sale->due_date,
            $sale->branch->branchGroup->company->name ?? '',
            $sale->branch->name ?? '',
            $sale->partner->name ?? '',
            $sale->currency->code ?? '',
            number_format($sale->exchange_rate, 6),
            number_format($sale->total_amount, 2),
            $this->getStatusLabel($sale->status),
            $sale->notes,
            $sale->creator->name ?? '',
            $sale->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getStatusLabel($status)
    {
        $statuses = [
            'open' => 'Belum Dibayar',
            'partially_paid' => 'Dibayar Sebagian',
            'paid' => 'Lunas',
        ];

        return $statuses[$status] ?? $status;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // Nomor Faktur
            'B' => 12, // Tanggal Faktur
            'C' => 12, // Jatuh Tempo
            'D' => 20, // Perusahaan
            'E' => 15, // Cabang
            'F' => 20, // Customer
            'G' => 12, // Mata Uang
            'H' => 12, // Nilai Tukar
            'I' => 15, // Total Faktur
            'J' => 15, // Status
            'K' => 25, // Catatan
            'L' => 15, // Dibuat Oleh
            'M' => 18, // Tanggal Dibuat
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB']
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
            'A:M' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            ],
            'B:C' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
            'G:I' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]],
            'M' => ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
        ];
    }
} 