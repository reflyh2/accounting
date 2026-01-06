<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
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

const showDeleteConfirmation = ref(false);

const reasonLookup = computed(() =>
    props.reasonOptions.reduce((carry, item) => ({ ...carry, [item.value]: item.label }), {})
);

const reasonLabel = computed(() =>
    props.purchaseReturn.reason_code
        ? (reasonLookup.value[props.purchaseReturn.reason_code] || props.purchaseReturn.reason_label)
        : '—'
);

function formatDate(date) {
    if (!date) return '—';
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}

function deletePurchaseReturn() {
    router.delete(route('purchase-returns.destroy', props.purchaseReturn.id), {
        onFinish: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Retur ${purchaseReturn.return_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Retur Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
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
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus Retur" />
                        </div>
                    </div>

                    <!-- Header Info Card -->
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Nomor Retur</span>
                                <span class="font-semibold text-gray-800">{{ purchaseReturn.return_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Status</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                    :class="purchaseReturn.status === 'posted' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'">
                                    {{ purchaseReturn.status === 'posted' ? 'Posted' : 'Draft' }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tanggal Retur</span>
                                <span class="text-gray-800">{{ formatDate(purchaseReturn.return_date) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Alasan</span>
                                <span class="text-gray-800">{{ reasonLabel }}</span>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Purchase Order</span>
                                <span class="text-gray-800">{{ purchaseReturn.purchase_order?.order_number || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Goods Receipt</span>
                                <span class="text-gray-800">{{ purchaseReturn.goods_receipt?.receipt_number || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Supplier</span>
                                <span class="text-gray-800">{{ purchaseReturn.partner?.name || '—' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Lokasi</span>
                                <span class="text-gray-800">{{ purchaseReturn.location?.name || '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="purchaseReturn.notes" class="bg-amber-50 rounded-lg border border-amber-200 p-4 text-sm">
                        <p class="font-semibold text-amber-800 mb-1">Catatan</p>
                        <p class="text-amber-700 whitespace-pre-line">{{ purchaseReturn.notes }}</p>
                    </div>

                    <!-- Lines Table -->
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Detail Barang</h3>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Barang</th>
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">Satuan</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">Qty Retur</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">Harga Satuan</th>
                                        <th class="px-3 py-2 text-right font-semibold text-gray-700">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="line in purchaseReturn.lines" :key="line.id">
                                        <td class="px-3 py-2 align-top">
                                            <div class="font-medium text-gray-900">{{ line.variant?.product_name || line.description }}</div>
                                            <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                                        </td>
                                        <td class="px-3 py-2 align-top text-gray-600">{{ line.uom?.code || '—' }}</td>
                                        <td class="px-3 py-2 text-right align-top">{{ formatNumber(line.quantity, 3) }}</td>
                                        <td class="px-3 py-2 text-right align-top">{{ formatNumber(line.unit_price, 2) }}</td>
                                        <td class="px-3 py-2 text-right align-top font-medium">{{ formatNumber(line.line_total, 2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                    <tr>
                                        <td colspan="4" class="px-3 py-2 text-right font-semibold">Total Nilai Retur:</td>
                                        <td class="px-3 py-2 text-right font-bold text-lg">{{ formatNumber(purchaseReturn.total_value, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Retur Pembelian"
            message="Apakah Anda yakin ingin menghapus retur pembelian ini? Tindakan ini akan membalikkan efek inventori dan akuntansi yang terkait."
            @close="showDeleteConfirmation = false"
            @confirm="deletePurchaseReturn"
        />
    </AuthenticatedLayout>
</template>

