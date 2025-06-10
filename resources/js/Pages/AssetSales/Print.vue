<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    assetSale: Object,
});

onMounted(() => {
    // Trigger print dialog automatically
    window.print();
});

const formattedStatus = computed(() => {
    const statusLabels = {
        'open': 'Belum Dibayar',
        'partially_paid': 'Dibayar Sebagian',
        'paid': 'Lunas'
    };
    return statusLabels[props.assetSale.status] || props.assetSale.status;
});

const totalAmount = computed(() => {
    return props.assetSale?.asset_invoice_details?.reduce((sum, detail) => {
        return sum + (Number(detail.quantity) * Number(detail.unit_price));
    }, 0) || 0;
});

</script>

<template>
    <Head :title="`Cetak Faktur ${assetSale.number}`" />
    <div class="p-6 bg-white text-gray-900 print:p-0 print:m-0 print:text-black print:bg-white">
        <h2 class="text-2xl font-bold mb-4 text-center">Faktur Penjualan Aset</h2>
        <h3 class="text-lg font-semibold mb-6 text-center">#{{ assetSale.number }}</h3>

        <!-- Invoice Header Info -->
        <div class="grid grid-cols-2 gap-x-8 gap-y-1 *:py-0.5 text-xs mb-6 border-b pb-4">
             <div>
                <p class="font-semibold">Perusahaan:</p>
                <p>{{ assetSale.branch?.branch_group?.company?.name }}</p>
            </div>
            <div>
                <p class="font-semibold">Customer:</p>
                <p>{{ assetSale.partner?.name }}</p>
            </div>
            <div>
                <p class="font-semibold">Cabang:</p>
                <p>{{ assetSale.branch?.name }}</p>
            </div>
            <div>
                <p class="font-semibold">Status:</p>
                <p>{{ formattedStatus }}</p>
            </div>
            <div>
                <p class="font-semibold">Tanggal Faktur:</p>
                <p>{{ new Date(assetSale.invoice_date).toLocaleDateString('id-ID') }}</p>
            </div>
            <div>
                <p class="font-semibold">Tanggal Jatuh Tempo:</p>
                <p>{{ new Date(assetSale.due_date).toLocaleDateString('id-ID') }}</p>
            </div>
            <div class="col-span-2">
                <p class="font-semibold">Catatan:</p>
                <p>{{ assetSale.notes || '-' }}</p>
            </div>
        </div>

        <!-- Invoice Details Table -->
        <div class="mt-6">
            <h4 class="text-md font-semibold mb-2">Detail Item Faktur</h4>
            <table class="w-full border-collapse border border-gray-400 text-xs">
                <thead>
                    <tr class="bg-gray-100 print:bg-gray-100">
                        <th class="border border-gray-400 px-1 py-1 text-left">Kode Aset</th>
                        <th class="border border-gray-400 px-1 py-1 text-left">Nama Aset</th>
                        <th class="border border-gray-400 px-1 py-1 text-left">Deskripsi</th>
                        <th class="border border-gray-400 px-1 py-1 text-right">Qty</th>
                        <th class="border border-gray-400 px-1 py-1 text-right" colspan="2">Harga Jual</th>
                        <th class="border border-gray-400 px-1 py-1 text-right" colspan="2">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="detail in assetSale.asset_invoice_details" :key="detail.id">
                        <td class="border border-gray-400 px-1 py-1">{{ detail.asset?.code }}</td>
                        <td class="border border-gray-400 px-1 py-1">{{ detail.asset?.name }}</td>
                        <td class="border border-gray-400 px-1 py-1">{{ detail.description }}</td>
                        <td class="border border-gray-400 px-1 py-1 text-right">{{ formatNumber(detail.quantity, 0) }}</td>
                        <td class="border border-r-0 border-gray-400 px-1 py-1 text-right">{{ assetSale.currency?.symbol }}</td>
                        <td class="border border-l-0 border-gray-400 px-1 py-1 text-right">{{ formatNumber(detail.unit_price) }}</td>
                        <td class="border border-r-0 border-gray-400 px-1 py-1 text-right">{{ assetSale.currency?.symbol }}</td>
                        <td class="border border-l-0 border-gray-400 px-1 py-1 text-right">{{ formatNumber(detail.line_amount) }}</td>
                    </tr>
                    <tr v-if="!assetSale.asset_invoice_details || assetSale.asset_invoice_details.length === 0">
                        <td colspan="8" class="border border-gray-400 px-1 py-1 text-center text-gray-500">Tidak ada detail item.</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 print:bg-gray-100">
                        <td colspan="6" class="border border-gray-400 px-1 py-1 text-right font-semibold">Total Faktur</td>
                        <td class="border border-r-0 border-gray-400 px-1 py-1 text-right font-semibold">{{ assetSale.currency?.symbol }}</td>
                        <td class="border border-l-0 border-gray-400 px-1 py-1 text-right font-semibold">{{ formatNumber(totalAmount) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Optional: Add signature section for print -->
        <div class="mt-12 text-xs grid grid-cols-3 gap-4">
            <div class="text-center">
                <p>Dibuat Oleh,</p>
                <div class="mt-12 border-t border-gray-400 pt-1">{{ assetSale.creator?.name ?? '(____________________)' }}</div>
            </div>
             <div class="text-center">
                <p>Disetujui Oleh,</p>
                <div class="mt-12 border-t border-gray-400 pt-1">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</div>
            </div>
            <div class="text-center">
                <p>Diterima Oleh,</p>
                 <div class="mt-12 border-t border-gray-400 pt-1">{{ assetSale.partner?.name ?? '(____________________)' }}</div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
  body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    margin: 0;
    padding: 0;
    background-color: white;
  }
  .print\:p-0 { padding: 0 !important; }
  .print\:m-0 { margin: 0 !important; }
  .print\:text-black { color: black !important; }
  .print\:bg-white { background-color: white !important; }
  .print\:bg-gray-100 { background-color: #f3f4f6 !important; } /* Match Tailwind gray-100 */
  table, th, td {
    border-color: #9ca3af !important; /* Match Tailwind gray-400 */
  }
  @page {
    size: A4; /* or Letter, etc. */
    margin: 1cm;
  }
}
</style> 