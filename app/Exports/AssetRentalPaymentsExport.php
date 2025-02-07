<?php

namespace App\Exports;

use App\Models\AssetRentalPayment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetRentalPaymentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return AssetRentalPayment::with('asset.branch.branchGroup.company')
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama Aset',
            'Perusahaan',
            'Grup Cabang',
            'Cabang',
            'Tanggal Bayar',
            'Jumlah',
            'Periode Mulai',
            'Periode Selesai',
            'Status',
            'Catatan',
            'Dibuat',
            'Diperbarui'
        ];
    }

    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->asset->name,
            $payment->asset->branch->branchGroup->company->name,
            $payment->asset->branch->branchGroup->name,
            $payment->asset->branch->name,
            $payment->payment_date ? date('d/m/Y', strtotime($payment->payment_date)) : '-',
            number_format($payment->amount, 2, ',', '.'),
            date('d/m/Y', strtotime($payment->period_start)),
            date('d/m/Y', strtotime($payment->period_end)),
            $this->getStatusLabel($payment->status),
            $payment->notes,
            $payment->created_at->format('d/m/Y H:i:s'),
            $payment->updated_at->format('d/m/Y H:i:s')
        ];
    }

    private function getStatusLabel($status)
    {
        return match($status) {
            'pending' => 'Menunggu',
            'paid' => 'Lunas',
            'overdue' => 'Terlambat',
            default => $status
        };
    }
} 