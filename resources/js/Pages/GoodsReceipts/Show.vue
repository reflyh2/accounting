<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';

const props = defineProps({
    goodsReceipt: Object,
    filters: Object,
});

const canCreateReturn = computed(() => Number(props.goodsReceipt?.returnable_quantity || 0) > 0);
</script>

<template>
    <Head :title="`Penerimaan Pembelian ${goodsReceipt.receipt_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-500">Penerimaan Pembelian</p>
                    <h2 class="text-2xl font-semibold">{{ goodsReceipt.receipt_number }}</h2>
                </div>
                <DocumentStatusPill
                    :documentKind="DocumentStatusKind.GOODS_RECEIPT"
                    :status="goodsReceipt.status"
                />
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <AppBackLink :href="route('goods-receipts.index', filters)" text="Kembali ke Daftar Penerimaan Pembelian" />
                        <Link
                            v-if="canCreateReturn"
                            :href="route('purchase-returns.create', { goods_receipt_id: goodsReceipt.id })"
                        >
                            <AppSecondaryButton as="span">
                                Buat Retur
                            </AppSecondaryButton>
                        </Link>
                    </div>

                    <div class="grid lg:grid-cols-3 gap-4">
                        <div class="border border-gray-200 rounded p-4 space-y-2">
                            <h3 class="text-sm font-semibold text-gray-600">Informasi Dokumen</h3>
                            <p><span class="text-gray-500 text-sm">Tanggal:</span> {{ goodsReceipt.receipt_date ? new Date(goodsReceipt.receipt_date).toLocaleDateString('id-ID') : '—' }}</p>
                            <p>
                                <span class="text-gray-500 text-sm">Purchase Order:</span>
                                <span v-if="goodsReceipt.purchase_order">
                                    <Link
                                        :href="route('purchase-orders.show', goodsReceipt.purchase_order.id)"
                                        class="text-main-600 hover:underline"
                                    >
                                        {{ goodsReceipt.purchase_order.order_number }}
                                    </Link>
                                </span>
                                <span v-else>—</span>
                            </p>
                            <p><span class="text-gray-500 text-sm">Lokasi:</span> {{ goodsReceipt.location?.name || '—' }}</p>
                            <p><span class="text-gray-500 text-sm">Metode Penilaian:</span> {{ goodsReceipt.valuation_method?.toUpperCase() || '—' }}</p>
                        </div>
                        <div class="border border-gray-200 rounded p-4 space-y-2">
                            <h3 class="text-sm font-semibold text-gray-600">Supplier & Cabang</h3>
                            <p><span class="text-gray-500 text-sm">Supplier:</span> {{ goodsReceipt.purchase_order?.partner?.name || '—' }}</p>
                            <p><span class="text-gray-500 text-sm">Cabang:</span> {{ goodsReceipt.purchase_order?.branch?.name || '—' }}</p>
                            <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ goodsReceipt.purchase_order?.branch?.company?.name || '—' }}</p>
                            <p>
                                <span class="text-gray-500 text-sm">Transaksi Persediaan:</span>
                                <span v-if="goodsReceipt.inventory_transaction">
                                    {{ goodsReceipt.inventory_transaction.transaction_number }}
                                </span>
                                <span v-else>—</span>
                            </p>
                        </div>
                        <div class="border border-gray-200 rounded p-4 space-y-2">
                            <h3 class="text-sm font-semibold text-gray-600">Ringkasan Nilai</h3>
                            <p class="flex justify-between text-sm">
                                <span>Total Qty</span>
                                <span>{{ formatNumber(goodsReceipt.total_quantity, 3) }}</span>
                            </p>
                            <p class="flex justify-between text-sm">
                                <span>Nilai ({{ goodsReceipt.purchase_order?.currency?.code || goodsReceipt.currency?.code || 'IDR' }})</span>
                                <span>{{ formatNumber(goodsReceipt.total_value) }}</span>
                            </p>
                            <p class="flex justify-between text-sm">
                                <span>Nilai (Base)</span>
                                <span>{{ formatNumber(goodsReceipt.total_value_base) }}</span>
                            </p>
                            <p class="flex justify-between text-sm">
                                <span>Diposting Pada</span>
                                <span>{{ goodsReceipt.posted_at || '—' }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="border border-gray-200 rounded">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Produk</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-600">Qty</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Satuan</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-600">Harga</th>
                                    <th class="px-4 py-2 text-right font-medium text-gray-600">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="line in goodsReceipt.lines" :key="line.id">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ line.variant?.product_name || line.description }}</div>
                                        <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber(line.quantity, 3) }}</td>
                                    <td class="px-4 py-3">{{ line.uom?.code || '—' }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber(line.unit_price) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber(line.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="goodsReceipt.notes" class="border border-gray-200 rounded p-4">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                        <p class="text-gray-700 whitespace-pre-line">{{ goodsReceipt.notes }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

