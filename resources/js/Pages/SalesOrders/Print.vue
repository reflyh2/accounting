<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    salesOrder: Object,
    template: Object,
    renderedContent: String,
});

const useCustomTemplate = computed(() => !!props.renderedContent);

onMounted(() => {
    window.print();
});

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}
</script>

<template>
    <Head :title="`Print SO ${salesOrder.order_number}`" />

    <!-- Custom Template Rendering -->
    <div v-if="useCustomTemplate" class="print-custom-template" v-html="renderedContent"></div>

    <!-- Default Hardcoded Template (Fallback) -->
    <div v-else class="print-layout">
        <div class="header">
            <div class="company-info">
                <h1>{{ salesOrder.branch?.branch_group?.company?.name }}</h1>
                <p>{{ salesOrder.branch?.name }}</p>
            </div>
            <div class="document-title">
                <h2>SALES ORDER</h2>
                <h3>{{ salesOrder.order_number }}</h3>
            </div>
        </div>

        <div class="document-details grid grid-cols-2 gap-4 mt-4">
            <div class="info-section">
                <h4 class="font-semibold mb-2 border-b border-gray-400 pb-1">Informasi Pelanggan</h4>
                <div class="flex py-0.5">
                    <span class="w-28 font-medium">Nama:</span>
                    <span>{{ salesOrder.partner?.name }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-28 font-medium">Kode:</span>
                    <span>{{ salesOrder.partner?.code }}</span>
                </div>
                <div v-if="salesOrder.partner?.address" class="flex py-0.5">
                    <span class="w-28 font-medium">Alamat:</span>
                    <span>{{ salesOrder.partner?.address }}</span>
                </div>
                <div v-if="salesOrder.customer_reference" class="flex py-0.5">
                    <span class="w-28 font-medium">Ref. Pelanggan:</span>
                    <span>{{ salesOrder.customer_reference }}</span>
                </div>
            </div>
            <div class="info-section">
                <h4 class="font-semibold mb-2 border-b border-gray-400 pb-1">Informasi Dokumen</h4>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Tanggal SO:</span>
                    <span>{{ formatDate(salesOrder.order_date) }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Estimasi Kirim:</span>
                    <span>{{ formatDate(salesOrder.expected_delivery_date) }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Berlaku Sampai:</span>
                    <span>{{ formatDate(salesOrder.quote_valid_until) }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Mata Uang:</span>
                    <span>{{ salesOrder.currency?.code }}</span>
                </div>
                <div v-if="salesOrder.payment_terms" class="flex py-0.5">
                    <span class="w-32 font-medium">Syarat Bayar:</span>
                    <span>{{ salesOrder.payment_terms }}</span>
                </div>
            </div>
        </div>

        <table class="items-table mt-4">
            <thead>
                <tr>
                    <th class="text-center w-10">No</th>
                    <th class="text-left">Produk</th>
                    <th class="text-left">Deskripsi</th>
                    <th class="text-right">Qty</th>
                    <th class="text-left">Satuan</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Pajak</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, index) in salesOrder.lines" :key="line.id">
                    <td class="text-center">{{ index + 1 }}</td>
                    <td>
                        <div>{{ line.variant?.product?.name }}</div>
                        <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                    </td>
                    <td>{{ line.description || '—' }}</td>
                    <td class="text-right">{{ formatNumber(line.quantity) }}</td>
                    <td>{{ line.uom?.code }}</td>
                    <td class="text-right">{{ formatNumber(line.unit_price) }}</td>
                    <td class="text-right">{{ formatNumber(line.tax_amount) }}</td>
                    <td class="text-right">{{ formatNumber(line.line_total) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6"></td>
                    <td class="text-right font-semibold">Subtotal:</td>
                    <td class="text-right">{{ formatNumber(salesOrder.subtotal) }}</td>
                </tr>
                <tr>
                    <td colspan="6"></td>
                    <td class="text-right font-semibold">Pajak:</td>
                    <td class="text-right">{{ formatNumber(salesOrder.tax_total) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="6"></td>
                    <td class="text-right font-bold">Total:</td>
                    <td class="text-right font-bold">{{ formatNumber(salesOrder.total_amount) }}</td>
                </tr>
            </tfoot>
        </table>

        <div v-if="salesOrder.notes" class="notes-section mt-4">
            <h4 class="font-semibold border-b border-gray-400 pb-1 mb-2">Catatan</h4>
            <p class="whitespace-pre-line">{{ salesOrder.notes }}</p>
        </div>

        <div class="signatures grid grid-cols-3 gap-4 mt-8">
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Dibuat Oleh</h4>
                <div class="pt-16 border-b border-gray-600 pb-2">{{ salesOrder.creator?.name || '&nbsp;' }}</div>
            </div>
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Disetujui Oleh</h4>
                <div class="pt-16 border-b border-gray-600 pb-2">&nbsp;</div>
            </div>
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Pelanggan</h4>
                <div class="pt-16 border-b border-gray-600 pb-2">&nbsp;</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .print-layout {
        font-family: Arial, sans-serif;
        font-size: 10pt;
        line-height: 1.35;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #000;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }

    .company-info h1 {
        font-size: 14pt;
        font-weight: bold;
        margin: 0;
    }

    .company-info p {
        margin: 2px 0 0 0;
        font-size: 10pt;
    }

    .document-title {
        text-align: right;
    }

    .document-title h2 {
        font-size: 14pt;
        font-weight: bold;
        margin: 0;
    }

    .document-title h3 {
        font-size: 12pt;
        margin: 4px 0 0 0;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
    }

    .items-table th {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 6px 8px;
        background-color: #f5f5f5;
        font-weight: 600;
    }

    .items-table td {
        padding: 4px 8px;
        border-bottom: 1px solid #ddd;
    }

    .items-table tfoot td {
        border-bottom: none;
        padding: 4px 8px;
    }

    .total-row td {
        border-top: 2px solid #000;
        padding-top: 8px;
    }

    .text-right {
        text-align: right;
    }

    .text-center {
        text-align: center;
    }

    @page {
        size: A4;
        margin: 1cm;
    }
}
</style>
