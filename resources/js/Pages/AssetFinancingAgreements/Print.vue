<script setup>
import { Head } from '@inertiajs/vue3';
import { formatNumber } from '@/utils/numberFormat';

defineProps({
    agreement: Object,
});
</script>

<template>
    <Head title="Print Perjanjian Pembiayaan Aset" />

    <div class="print-page">
        <div class="print-header text-center mb-6">
            <h1 class="text-2xl font-bold">PERJANJIAN PEMBIAYAAN ASET</h1>
            <h2 class="text-lg font-semibold">{{ agreement.number }}</h2>
        </div>

        <div class="print-content">
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Informasi Perjanjian</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <strong>Perusahaan:</strong>
                        <span class="ml-2">{{ agreement.branch.branch_group.company.name }}</span>
                    </div>
                    <div>
                        <strong>Cabang:</strong>
                        <span class="ml-2">{{ agreement.branch.name }}</span>
                    </div>
                    <div>
                        <strong>Tanggal Perjanjian:</strong>
                        <span class="ml-2">{{ new Date(agreement.agreement_date).toLocaleDateString('id-ID') }}</span>
                    </div>
                    <div>
                        <strong>Kreditor:</strong>
                        <span class="ml-2">{{ agreement.creditor.name }}</span>
                    </div>
                    <div>
                        <strong>Total Jumlah:</strong>
                        <span class="ml-2">{{ formatNumber(agreement.total_amount) }}</span>
                    </div>
                    <div>
                        <strong>Suku Bunga:</strong>
                        <span class="ml-2">{{ formatNumber(agreement.interest_rate) }}%</span>
                    </div>
                    <div>
                        <strong>Tanggal Mulai:</strong>
                        <span class="ml-2">{{ new Date(agreement.start_date).toLocaleDateString('id-ID') }}</span>
                    </div>
                    <div>
                        <strong>Tanggal Selesai:</strong>
                        <span class="ml-2">{{ new Date(agreement.end_date).toLocaleDateString('id-ID') }}</span>
                    </div>
                    <div>
                        <strong>Frekuensi Pembayaran:</strong>
                        <span class="ml-2">{{ agreement.payment_frequency_label }}</span>
                    </div>
                    <div>
                        <strong>Status:</strong>
                        <span class="ml-2">{{ agreement.status_label }}</span>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4">Detail Invoice Aset</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <strong>Nomor Invoice:</strong>
                            <span class="ml-2">{{ agreement.asset_invoice.invoice_number }}</span>
                        </div>
                        <div>
                            <strong>Tanggal Invoice:</strong>
                            <span class="ml-2">{{ new Date(agreement.asset_invoice.invoice_date).toLocaleDateString('id-ID') }}</span>
                        </div>
                        <div>
                            <strong>Aset:</strong>
                            <span class="ml-2">{{ agreement.asset_invoice.asset_invoice_details?.[0]?.asset?.name || 'No Asset' }}</span>
                        </div>
                        <div>
                            <strong>Vendor:</strong>
                            <span class="ml-2">{{ agreement.asset_invoice.partner.name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="agreement.notes" class="mb-6">
                <h3 class="text-lg font-semibold mb-2">Catatan</h3>
                <p class="text-sm">{{ agreement.notes }}</p>
            </div>

            <div class="mt-8 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-2 gap-4 text-xs text-gray-500">
                    <div v-if="agreement.created_by">
                        <strong>Dibuat oleh:</strong>
                        <span class="ml-2">{{ agreement.created_by?.name }} - {{ new Date(agreement.created_at).toLocaleDateString('id-ID') }}</span>
                    </div>
                    <div v-if="agreement.updated_by">
                        <strong>Diubah oleh:</strong>
                        <span class="ml-2">{{ agreement.updated_by?.name }} - {{ new Date(agreement.updated_at).toLocaleDateString('id-ID') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .print-page {
        font-size: 12px;
    }
    
    .print-header {
        margin-bottom: 20px;
    }
}
</style> 