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
import AppModal from '@/Components/AppModal.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    salesOrder: Object,
    filters: Object,
    allowedTransitions: Array,
});

const showCancelModal = ref(false);
const showDeleteConfirmation = ref(false);
const cancelReason = ref('');
const processing = ref(false);

const canQuote = computed(() => props.allowedTransitions?.includes('quote'));
const canConfirm = computed(() => props.allowedTransitions?.includes('confirmed'));
const canCancel = computed(() => props.allowedTransitions?.includes('canceled'));
const isDraft = computed(() => props.salesOrder.status === 'draft');
const canReserve = computed(
    () =>
        props.salesOrder.reserve_stock &&
        !props.salesOrder.reservation_applied_at &&
        props.salesOrder.status === 'confirmed'
);
const canReleaseReservation = computed(() => !!props.salesOrder.reservation_applied_at);
const hasDeliverableLines = computed(() =>
    props.salesOrder?.lines?.some(
        (line) => Number(line.quantity) - Number(line.quantity_delivered) > 0
    )
);
const canCreateDelivery = computed(() =>
    ['confirmed', 'partially_delivered'].includes(props.salesOrder.status) && hasDeliverableLines.value
);

function submitAction(routeName) {
    processing.value = true;
    router.post(route(routeName, props.salesOrder.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

function openCancelModal() {
    showCancelModal.value = true;
}

function confirmCancel() {
    processing.value = true;
    router.post(route('sales-orders.cancel', props.salesOrder.id), {
        reason: cancelReason.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            showCancelModal.value = false;
            cancelReason.value = '';
        },
    });
}

function deleteOrder() {
    router.delete(route('sales-orders.destroy', props.salesOrder.id), {
        onFinish: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Sales Order ${salesOrder.order_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Sales Order</p>
                    <h2 class="text-2xl font-semibold">
                        {{ salesOrder.order_number }}
                    </h2>
                </div>
                <DocumentStatusPill
                    :documentKind="DocumentStatusKind.SALES_ORDER"
                    :status="salesOrder.status"
                />
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <AppBackLink :href="route('sales-orders.index', filters)" text="Kembali ke Daftar Sales Order" />
                            <div class="flex flex-wrap items-center">
                                <a :href="route('sales-orders.print', salesOrder.id)" target="_blank">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link v-if="isDraft" :href="route('sales-orders.edit', salesOrder.id)" class="ml-3">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppPrimaryButton v-if="canQuote" type="button" @click="submitAction('sales-orders.quote')" :disabled="processing" class="ml-3">
                                    Tandai Sebagai Quote
                                </AppPrimaryButton>
                                <AppPrimaryButton v-if="canConfirm" type="button" @click="submitAction('sales-orders.confirm')" :disabled="processing" class="ml-3">
                                    Konfirmasi Order
                                </AppPrimaryButton>
                                <Link
                                    v-if="canCreateDelivery"
                                    :href="route('sales-deliveries.create', { sales_order_id: salesOrder.id })"
                                    class="ml-3"
                                >
                                    <AppPrimaryButton type="button">
                                        Buat Delivery
                                    </AppPrimaryButton>
                                </Link>
                                <AppSecondaryButton v-if="canReserve" type="button" @click="submitAction('sales-orders.reserve')" :disabled="processing" class="ml-3">
                                    Reservasi Stok
                                </AppSecondaryButton>
                                <AppSecondaryButton v-if="canReleaseReservation" type="button" @click="submitAction('sales-orders.release-reservation')" :disabled="processing" class="ml-3">
                                    Lepaskan Reservasi
                                </AppSecondaryButton>
                                <AppDangerButton v-if="canCancel" type="button" @click="openCancelModal" :disabled="processing" class="ml-3">
                                    Batalkan
                                </AppDangerButton>
                                <AppDeleteButton v-if="isDraft" @click="showDeleteConfirmation = true" title="Delete" class="ml-3" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Dokumen</h3>
                                <p><span class="text-gray-500 text-sm">Tanggal Order:</span> {{ new Date(salesOrder.order_date).toLocaleDateString('id-ID') }}</p>
                                <p><span class="text-gray-500 text-sm">Perkiraan Kirim:</span> {{ salesOrder.expected_delivery_date ? new Date(salesOrder.expected_delivery_date).toLocaleDateString('id-ID') : '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Referensi Pelanggan:</span> {{ salesOrder.customer_reference || '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Syarat Pembayaran:</span> {{ salesOrder.payment_terms || '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Channel:</span> {{ salesOrder.sales_channel || '—' }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Pelanggan & Cabang</h3>
                                <p><span class="text-gray-500 text-sm">Pelanggan:</span> {{ salesOrder.partner?.name }} ({{ salesOrder.partner?.code }})</p>
                                <p><span class="text-gray-500 text-sm">Cabang:</span> {{ salesOrder.branch?.name }}</p>
                                <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ salesOrder.branch?.branch_group?.company?.name }}</p>
                                <p>
                                    <span class="text-gray-500 text-sm">Reservasi Stok:</span>
                                    {{ salesOrder.reserve_stock ? 'Ya' : 'Tidak' }}
                                    <span v-if="salesOrder.reserve_stock && salesOrder.reservation_applied_at" class="text-xs text-gray-500">
                                        • Diterapkan {{ new Date(salesOrder.reservation_applied_at).toLocaleString('id-ID') }}
                                    </span>
                                </p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Ringkasan Nilai</h3>
                                <p class="flex justify-between text-sm">
                                    <span>Subtotal</span>
                                    <span>{{ formatNumber(salesOrder.subtotal) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Total Pajak</span>
                                    <span>{{ formatNumber(salesOrder.tax_total) }}</span>
                                </p>
                                <p class="flex justify-between text-base font-semibold">
                                    <span>Grand Total</span>
                                    <span>{{ formatNumber(salesOrder.total_amount) }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Produk</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Deskripsi</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Qty Order</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Qty Terkirim</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Sisa</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Satuan</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Harga</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Pajak</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="line in salesOrder.lines" :key="line.id">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ line.product?.name || line.variant?.product?.name }}</div>
                                            <div class="text-xs text-gray-500">{{ line.variant?.sku || '—' }}</div>
                                        </td>
                                        <td class="px-4 py-3">{{ line.description || '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.quantity) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(line.quantity_delivered) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            {{
                                                formatNumber(
                                                    Math.max(Number(line.quantity || 0) - Number(line.quantity_delivered || 0), 0)
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

                        <div v-if="salesOrder.notes" class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                            <p class="text-gray-700 whitespace-pre-line">{{ salesOrder.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Modal -->
        <AppModal :show="showCancelModal" @close="showCancelModal = false">
            <template #title>
                Batalkan Sales Order
            </template>

            <template #content>
                <div class="mt-2">
                    <p class="text-sm text-gray-500 mb-4">
                        Apakah Anda yakin ingin membatalkan sales order ini? Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <AppTextarea
                        v-model="cancelReason"
                        label="Alasan Pembatalan (opsional)"
                        placeholder="Masukkan alasan pembatalan..."
                        :rows="3"
                    />
                </div>
            </template>

            <template #footer>
                <AppSecondaryButton @click="showCancelModal = false">Tidak</AppSecondaryButton>
                <AppDangerButton class="ml-3" @click="confirmCancel" :disabled="processing">Ya, Batalkan</AppDangerButton>
            </template>
        </AppModal>

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Sales Order"
            message="Apakah Anda yakin ingin menghapus sales order ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteOrder"
        />
    </AuthenticatedLayout>
</template>
