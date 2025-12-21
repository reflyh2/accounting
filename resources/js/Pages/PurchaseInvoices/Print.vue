<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    purchaseInvoice: Object,
});

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

function statusLabel(status) {
    const labels = {
        draft: 'Draft',
        posted: 'Posted',
        paid: 'Lunas',
        partial: 'Sebagian Dibayar',
        void: 'Dibatalkan',
    };
    return labels[status] || status;
}
</script>

<template>
    <Head :title="`Print Faktur ${purchaseInvoice.invoice_number}`" />

    <div class="print-layout">
        <div class="header">
            <div class="company-info">
                <h1>{{ purchaseInvoice.company?.name || purchaseInvoice.branch?.branch_group?.company?.name }}</h1>
                <p>{{ purchaseInvoice.branch?.name }}</p>
            </div>
            <div class="document-title">
                <h2>FAKTUR PEMBELIAN</h2>
                <h3>{{ purchaseInvoice.invoice_number }}</h3>
                <div class="text-sm mt-1">Status: {{ statusLabel(purchaseInvoice.status) }}</div>
            </div>
        </div>

        <div class="document-details grid grid-cols-2 gap-4 mt-4">
            <div class="info-section">
                <h4 class="font-semibold mb-2 border-b border-gray-400 pb-1">Informasi Supplier</h4>
                <div class="flex py-0.5">
                    <span class="w-28 font-medium">Nama:</span>
                    <span>{{ purchaseInvoice.partner?.name }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-28 font-medium">Kode:</span>
                    <span>{{ purchaseInvoice.partner?.code }}</span>
                </div>
                <div v-if="purchaseInvoice.partner?.address" class="flex py-0.5">
                    <span class="w-28 font-medium">Alamat:</span>
                    <span>{{ purchaseInvoice.partner?.address }}</span>
                </div>
            </div>
            <div class="info-section">
                <h4 class="font-semibold mb-2 border-b border-gray-400 pb-1">Informasi Dokumen</h4>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Tanggal Faktur:</span>
                    <span>{{ formatDate(purchaseInvoice.invoice_date) }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Jatuh Tempo:</span>
                    <span>{{ formatDate(purchaseInvoice.due_date) }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Ref. Supplier:</span>
                    <span>{{ purchaseInvoice.supplier_reference || '—' }}</span>
                </div>
                <div class="flex py-0.5">
                    <span class="w-32 font-medium">Mata Uang:</span>
                    <span>{{ purchaseInvoice.currency?.code }}</span>
                </div>
                <div v-if="purchaseInvoice.exchange_rate && purchaseInvoice.exchange_rate != 1" class="flex py-0.5">
                    <span class="w-32 font-medium">Kurs:</span>
                    <span>{{ formatNumber(purchaseInvoice.exchange_rate) }}</span>
                </div>
                <div v-if="purchaseInvoice.payment_method" class="flex py-0.5">
                    <span class="w-32 font-medium">Metode Bayar:</span>
                    <span class="capitalize">{{ purchaseInvoice.payment_method }}</span>
                </div>
            </div>
        </div>

        <!-- Purchase Order References -->
        <div v-if="purchaseInvoice.purchase_orders?.length" class="mt-4">
            <h4 class="font-semibold border-b border-gray-400 pb-1 mb-2">Referensi Purchase Order</h4>
            <div class="text-sm">
                {{ purchaseInvoice.purchase_orders.map(po => po.order_number).join(', ') }}
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
                <tr v-for="(line, index) in purchaseInvoice.lines" :key="line.id">
                    <td class="text-center">{{ index + 1 }}</td>
                    <td>
                        <div>{{ line.variant?.product?.name || line.product_variant?.product?.name }}</div>
                        <div class="text-xs text-gray-500">{{ line.variant?.sku || line.product_variant?.sku }}</div>
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
                    <td class="text-right">{{ formatNumber(purchaseInvoice.subtotal) }}</td>
                </tr>
                <tr>
                    <td colspan="6"></td>
                    <td class="text-right font-semibold">Pajak:</td>
                    <td class="text-right">{{ formatNumber(purchaseInvoice.tax_total) }}</td>
                </tr>
                <tr class="total-row">
                    <td colspan="6"></td>
                    <td class="text-right font-bold">Total:</td>
                    <td class="text-right font-bold">{{ formatNumber(purchaseInvoice.total_amount) }}</td>
                </tr>
            </tfoot>
        </table>

        <div v-if="purchaseInvoice.notes" class="notes-section mt-4">
            <h4 class="font-semibold border-b border-gray-400 pb-1 mb-2">Catatan</h4>
            <p class="whitespace-pre-line">{{ purchaseInvoice.notes }}</p>
        </div>

        <div class="signatures grid grid-cols-3 gap-4 mt-8">
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Dibuat Oleh</h4>
                <div class="pt-16 border-b border-gray-600 pb-2">{{ purchaseInvoice.creator?.name || '&nbsp;' }}</div>
            </div>
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Disetujui Oleh</h4>
                <div class="pt-16 border-b border-gray-600 pb-2">&nbsp;</div>
            </div>
            <div class="signature-box text-center">
                <h4 class="font-semibold border-b border-gray-600 pb-2">Supplier</h4>
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
