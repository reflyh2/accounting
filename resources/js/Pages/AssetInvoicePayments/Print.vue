<script setup>
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    payment: Object,
    paymentMethods: Object,
    paymentTypes: Object,
});
</script>

<template>
    <div class="min-h-screen bg-white p-8 print:p-0">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold">Bukti Pembayaran Faktur Aset</h1>
                <h2 class="text-xl text-gray-600">{{ payment.number }}</h2>
            </div>

            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">Informasi Pembayaran</h3>
                    <table class="w-full text-sm">
                        <tr>
                            <td class="py-1 font-medium">Tanggal Bayar:</td>
                            <td class="py-1">{{ new Date(payment.payment_date).toLocaleDateString('id-ID') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-medium">Tipe:</td>
                            <td class="py-1">{{ paymentTypes[payment.type] || payment.type }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-medium">Referensi:</td>
                            <td class="py-1">{{ payment.reference || '-' }}</td>
                        </tr>
                        <tr>
                            <td class="py-1 font-medium">Metode Bayar:</td>
                            <td class="py-1">{{ paymentMethods[payment.payment_method] || payment.payment_method }}</td>
                        </tr>
                    </table>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Partner</h3>
                    <div class="text-sm">
                        <p class="font-medium">{{ payment.partner.name }}</p>
                        <p>{{ payment.partner.address || '' }}</p>
                        <p>{{ payment.partner.phone || '' }}</p>
                        <p>{{ payment.partner.email || '' }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-8">
                <h3 class="text-lg font-semibold mb-4">Alokasi Pembayaran</h3>
                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left">Nomor Faktur</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Tanggal Faktur</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Cabang</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Total Faktur</th>
                            <th class="border border-gray-300 px-4 py-2 text-right">Jumlah Dibayar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="allocation in payment.allocations" :key="allocation.id">
                            <td class="border border-gray-300 px-4 py-2">{{ allocation.asset_invoice.number }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ new Date(allocation.asset_invoice.invoice_date).toLocaleDateString('id-ID') }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ allocation.asset_invoice.branch?.name || '-' }}</td>
                            <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(allocation.asset_invoice.total_amount) }}</td>
                            <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(allocation.allocated_amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="4" class="border border-gray-300 px-4 py-2 text-right">Total Dibayar:</td>
                            <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(payment.amount) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="grid grid-cols-3 gap-8 mt-16">
                <div class="text-center">
                    <div class="border-t border-gray-400 pt-2 mt-16">
                        <p class="text-sm">Dibuat Oleh</p>
                        <p class="text-xs text-gray-600">{{ payment.creator?.name || 'System' }}</p>
                    </div>
                </div>
                <div class="text-center">
                    <div class="border-t border-gray-400 pt-2 mt-16">
                        <p class="text-sm">Disetujui Oleh</p>
                        <p class="text-xs text-gray-600">_________________</p>
                    </div>
                </div>
                <div class="text-center">
                    <div class="border-t border-gray-400 pt-2 mt-16">
                        <p class="text-sm">Penerima</p>
                        <p class="text-xs text-gray-600">_________________</p>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center text-xs text-gray-500">
                <p>Dicetak pada: {{ new Date().toLocaleString('id-ID') }}</p>
                <p v-if="payment.notes" class="mt-2">Catatan: {{ payment.notes }}</p>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    @page {
        margin: 0.5in;
    }
    
    .print\:p-0 {
        padding: 0 !important;
    }
}
</style> 