<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    purchaseReturn: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    reasonOptions: {
        type: Array,
        default: () => [],
    },
});

const reasonLookup = computed(() =>
    props.reasonOptions.reduce((carry, item) => ({ ...carry, [item.value]: item.label }), {})
);

const reasonLabel = computed(() =>
    props.purchaseReturn.reason_code
        ? (reasonLookup.value[props.purchaseReturn.reason_code] || props.purchaseReturn.reason_label)
        : '-'
);
</script>

<template>
    <Head :title="`Retur ${purchaseReturn.return_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Retur Pembelian</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <AppBackLink :href="route('purchase-returns.index', filters)" text="Kembali ke Daftar Retur" />
                        <div class="flex gap-2">
                            <Link
                                v-if="purchaseReturn.goods_receipt?.id"
                                :href="route('goods-receipts.show', purchaseReturn.goods_receipt.id)"
                            >
                                <AppSecondaryButton as="span">
                                    Lihat GRN
                                </AppSecondaryButton>
                            </Link>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <div>
                                <p class="text-lg font-semibold">{{ purchaseReturn.return_number }}</p>
                                <p class="text-sm text-gray-500">Tanggal: {{ purchaseReturn.return_date || '-' }}</p>
                            </div>
                            <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                :class="purchaseReturn.status === 'posted' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
                                {{ purchaseReturn.status === 'posted' ? 'Posted' : 'Draft' }}
                            </span>
                        </div>

                        <div class="grid md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="font-semibold">Purchase Order</p>
                                <p>{{ purchaseReturn.purchase_order?.order_number || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Goods Receipt</p>
                                <p>{{ purchaseReturn.goods_receipt?.receipt_number || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Supplier</p>
                                <p>{{ purchaseReturn.partner?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Lokasi</p>
                                <p>{{ purchaseReturn.location?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Alasan</p>
                                <p>{{ reasonLabel }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan</p>
                                <p>{{ purchaseReturn.notes || '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-2">Detail Barang</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 border border-gray-200 text-left">Barang</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Qty Retur</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Harga</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in purchaseReturn.lines" :key="line.id" class="border-t">
                                        <td class="px-3 py-2 border border-gray-200 align-top">
                                            <div class="font-medium">{{ line.variant?.product_name || line.description }}</div>
                                            <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ formatNumber(line.quantity) }} {{ line.uom?.code || '' }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ formatNumber(line.unit_price, 2) }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right align-top">
                                            {{ formatNumber(line.line_total, 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td class="px-3 py-2 border border-gray-200 text-right" colspan="3">Total</td>
                                        <td class="px-3 py-2 border border-gray-200 text-right">
                                            {{ formatNumber(purchaseReturn.total_value, 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


