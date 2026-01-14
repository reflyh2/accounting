<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    salesDelivery: Object,
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
    <Head :title="`Print Delivery ${salesDelivery.delivery_number}`" />

    <!-- Custom Template Rendering -->
    <div v-if="useCustomTemplate" class="print-custom-template" v-html="renderedContent"></div>

    <!-- Default Hardcoded Template (Fallback) -->
    <div v-else class="print-layout">
        <div class="header">
            <div class="company-info">
                <h1>{{ salesDelivery.branch?.branch_group?.company?.name }}</h1>
                <p>{{ salesDelivery.branch?.name }}</p>
            </div>
            <div class="document-title">
                <h2>SURAT JALAN</h2>
                <h3>{{ salesDelivery.delivery_number }}</h3>
            </div>
        </div>

        <div class="document-details grid grid-cols-2 gap-4 mt-4">
            <div class="info-section">
                <h4 class="font-semibold mb-2 border-b border-gray-400 pb-1">Informasi Pelanggan</h4>
                <div class="flex py-0.5">
                    <span class="w-28 font-medium">Nama:</span>
                    <span>{{ salesDelivery.partner?.name }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-28 font-medium">Kode:</span>
                    <span>{{ salesDelivery.partner?.code }}</span>
                </div>
            </div>
            <div class="info-section">
                <h4 class="font-semibold mb-2 border-b border-gray-400 pb-1">Informasi Dokumen</h4>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Tanggal Kirim:</span>
                    <span>{{ formatDate(salesDelivery.delivery_date) }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Lokasi:</span>
                    <span>{{ salesDelivery.location?.name }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">No. SO:</span>
                    <span>{{ salesDelivery.sales_orders?.map(so => so.order_number).join(', ') }}</span>
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
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, index) in salesDelivery.lines" :key="line.id">
                    <td class="text-center">{{ index + 1 }}</td>
                    <td>
                        <div>{{ line.variant?.product?.name || line.description }}</div>
                        <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                    </td>
                    <td>{{ line.description || '—' }}</td>
                    <td class="text-right">{{ formatNumber(line.quantity, 2) }}</td>
                    <td>{{ line.uom?.code }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right font-semibold">Total Qty:</td>
                    <td class="text-right font-bold">{{ formatNumber(salesDelivery.total_quantity, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div v-if="salesDelivery.notes" class="notes-section mt-4">
            <h4 class="font-semibold border-b border-gray-400 pb-1 mb-2">Catatan</h4>
            <p class="whitespace-pre-line">{{ salesDelivery.notes }}</p>
        </div>

        <div class="signatures grid grid-cols-3 gap-4 mt-8">
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Dikirim Oleh</h4>
                <div class="pt-16 border-b border-gray-600 pb-2">{{ salesDelivery.creator?.name || '&nbsp;' }}</div>
            </div>
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Kurir</h4>
                <div class="pt-16 border-b border-gray-600 pb-2">&nbsp;</div>
            </div>
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Diterima Oleh</h4>
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
