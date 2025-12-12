<script setup>
import { computed, ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    purchaseOrder: Object,
    filters: Object,
    allowedTransitions: Array,
    makerCheckerEnforced: Boolean,
});

const cancelReason = ref('');

const canApprove = computed(() => props.allowedTransitions?.includes('approved'));
const canSend = computed(() => props.allowedTransitions?.includes('sent'));
const canCancel = computed(() => props.allowedTransitions?.includes('canceled'));
const isDraft = computed(() => props.purchaseOrder.status === 'draft');
const hasOutstandingReceipt = computed(() =>
    props.purchaseOrder.lines?.some((line) => {
        const ordered = Number(line.quantity || 0);
        const received = Number(line.quantity_received || 0);
        return ordered - received > 0.0001;
    })
);
const canCreateGoodsReceipt = computed(() =>
    hasOutstandingReceipt.value && ['sent', 'partially_received'].includes(props.purchaseOrder.status)
);

function approve() {
    router.post(route('purchase-orders.approve', props.purchaseOrder.id));
}

function markSent() {
    router.post(route('purchase-orders.send', props.purchaseOrder.id));
}

function cancelOrder() {
    router.post(route('purchase-orders.cancel', props.purchaseOrder.id), {
        reason: cancelReason.value || null,
    });
}

function deleteOrder() {
    if (confirm('Hapus Purchase Order ini?')) {
        router.delete(route('purchase-orders.destroy', props.purchaseOrder.id));
    }
}
</script>

<template>
    <Head :title="`Purchase Order ${purchaseOrder.order_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Purchase Order</p>
                    <h2 class="text-2xl font-semibold">
                        {{ purchaseOrder.order_number }}                    
                    </h2>
                </div>
                <DocumentStatusPill
                    :documentKind="DocumentStatusKind.PURCHASE_ORDER"
                    :status="purchaseOrder.status"
                />
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <AppBackLink :href="route('purchase-orders.index', filters)" text="Kembali ke Daftar Purchase Order" />
                            <div class="flex flex-wrap">
                                <a :href="route('purchase-orders.print', purchaseOrder.id)" target="_blank">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link v-if="isDraft" :href="route('purchase-orders.edit', purchaseOrder.id)" class="ml-3">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppPrimaryButton v-if="canApprove" type="button" @click="approve" class="ml-3">
                                    Approve
                                </AppPrimaryButton>
                                <AppPrimaryButton v-if="canSend" type="button" @click="markSent" class="ml-3">
                                    Tandai Terkirim
                                </AppPrimaryButton>
                                <Link
                                    v-if="canCreateGoodsReceipt"
                                    :href="route('goods-receipts.create', { partner_id: purchaseOrder.partner_id, purchase_order_ids: [purchaseOrder.id] })"
                                    class="ml-3"
                                >
                                    <AppPrimaryButton type="button">
                                        Buat Penerimaan Pembelian
                                    </AppPrimaryButton>
                                </Link>
                                <div v-if="canCancel" class="flex items-center gap-2">
                                    <input
                                        v-model="cancelReason"
                                        type="text"
                                        placeholder="Alasan pembatalan"
                                        class="border border-gray-300 rounded px-2 py-1 text-sm"
                                    />
                                    <AppDangerButton type="button" @click="cancelOrder">
                                        Batalkan
                                    </AppDangerButton>
                                </div>
                                <AppDeleteButton v-if="isDraft && !canApprove" @click="deleteOrder" title="Delete" />
                            </div>
                        </div>

                        <div v-if="makerCheckerEnforced" class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded">
                            Maker-checker diaktifkan. Pengaju tidak dapat menyetujui PO yang sama.
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Dokumen</h3>
                                <p><span class="text-gray-500 text-sm">Tanggal PO:</span> {{ new Date(purchaseOrder.order_date).toLocaleDateString('id-ID') }}</p>
                                <p><span class="text-gray-500 text-sm">Estimasi Kedatangan:</span> {{ purchaseOrder.expected_date ? new Date(purchaseOrder.expected_date).toLocaleDateString('id-ID') : '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Referensi Supplier:</span> {{ purchaseOrder.supplier_reference || '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Syarat Pembayaran:</span> {{ purchaseOrder.payment_terms || '—' }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Supplier & Cabang</h3>
                                <p><span class="text-gray-500 text-sm">Supplier:</span> {{ purchaseOrder.partner?.name }} ({{ purchaseOrder.partner?.code }})</p>
                                <p><span class="text-gray-500 text-sm">Cabang:</span> {{ purchaseOrder.branch?.name }}</p>
                                <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ purchaseOrder.branch?.branch_group?.company?.name }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Ringkasan Nilai</h3>
                                <p class="flex justify-between text-sm">
                                    <span>Subtotal</span>
                                    <span>{{ formatNumber(purchaseOrder.subtotal) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Total Pajak</span>
                                    <span>{{ formatNumber(purchaseOrder.tax_total) }}</span>
                                </p>
                                <p class="flex justify-between text-base font-semibold">
                                    <span>Grand Total</span>
                                    <span>{{ formatNumber(purchaseOrder.total_amount) }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Produk</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Deskripsi</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Qty PO</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Qty Diterima</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Sisa</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Satuan</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Harga</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Pajak</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="line in purchaseOrder.lines" :key="line.id">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ line.variant?.product?.name }}</div>
                                            <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                                        </td>
                                        <td class="px-4 py-3">{{ line.description || '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.quantity) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.quantity_received) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            {{
                                                formatNumber(
                                                    Math.max(Number(line.quantity || 0) - Number(line.quantity_received || 0), 0)
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">{{ line.uom?.code }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.unit_price) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.tax_amount) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.line_total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="purchaseOrder.notes" class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                            <p class="text-gray-700 whitespace-pre-line">{{ purchaseOrder.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

