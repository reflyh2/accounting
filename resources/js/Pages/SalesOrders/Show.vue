<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    salesOrder: Object,
    filters: Object,
    allowedTransitions: Array,
});

const actionForm = useForm({});

const canQuote = computed(() => props.allowedTransitions?.includes('quote'));
const canConfirm = computed(() => props.allowedTransitions?.includes('confirmed'));
const canCancel = computed(() => props.allowedTransitions?.includes('canceled'));
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

function submitAction(routeName, payload = {}) {
    actionForm.post(route(routeName, props.salesOrder.id), {
        preserveScroll: true,
        data: payload,
    });
}

function cancelOrder() {
    if (!confirm('Batalkan Sales Order ini?')) {
        return;
    }

    const reason = window.prompt('Alasan pembatalan (opsional)', '') || '';
    submitAction('sales-orders.cancel', { reason });
}

const amountSummary = computed(() => ({
    subtotal: props.salesOrder.subtotal || 0,
    tax: props.salesOrder.tax_total || 0,
    total: props.salesOrder.total_amount || 0,
}));
</script>

<template>
    <Head title="Detail Sales Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Sales Order</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6 space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <AppBackLink :href="route('sales-orders.index', filters)" text="Kembali ke Daftar Sales Order" />
                        <h3 class="text-xl font-semibold mt-2">{{ salesOrder.order_number }}</h3>
                        <p class="text-sm text-gray-500">Dibuat pada {{ new Date(salesOrder.order_date).toLocaleDateString('id-ID') }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <DocumentStatusPill
                            :documentKind="DocumentStatusKind.SALES_ORDER"
                            :status="salesOrder.status"
                            size="md"
                        />
                        <Link :href="route('sales-orders.edit', salesOrder.id)">
                            <AppEditButton title="Ubah" />
                        </Link>
                        <AppPrimaryButton v-if="canQuote" @click="submitAction('sales-orders.quote')">
                            Tandai Sebagai Quote
                        </AppPrimaryButton>
                        <AppPrimaryButton v-if="canConfirm" @click="submitAction('sales-orders.confirm')">
                            Konfirmasi Order
                        </AppPrimaryButton>
                        <Link
                            v-if="canCreateDelivery"
                            :href="route('sales-deliveries.create', { sales_order_id: salesOrder.id })"
                        >
                            <AppPrimaryButton>
                                Buat Delivery
                            </AppPrimaryButton>
                        </Link>
                        <AppSecondaryButton v-if="canReserve" @click="submitAction('sales-orders.reserve')">
                            Reservasi Stok
                        </AppSecondaryButton>
                        <AppSecondaryButton
                            v-if="canReleaseReservation"
                            @click="submitAction('sales-orders.release-reservation')"
                        >
                            Lepaskan Reservasi
                        </AppSecondaryButton>
                        <AppDangerButton v-if="canCancel" @click="cancelOrder">
                            Batalkan
                        </AppDangerButton>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2 text-sm">
                        <div>
                            <p class="text-gray-500">Perusahaan</p>
                            <p class="font-semibold">{{ salesOrder.branch?.branch_group?.company?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Cabang</p>
                            <p class="font-semibold">{{ salesOrder.branch?.name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Pelanggan</p>
                            <p class="font-semibold">{{ salesOrder.partner?.name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Channel</p>
                            <p class="font-semibold">{{ salesOrder.sales_channel || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Perkiraan Kirim</p>
                            <p class="font-semibold">
                                {{ salesOrder.expected_delivery_date
                                    ? new Date(salesOrder.expected_delivery_date).toLocaleDateString('id-ID')
                                    : '-' }}
                            </p>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div>
                            <p class="text-gray-500">Daftar Harga</p>
                            <p class="font-semibold">{{ salesOrder.price_list?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Referensi Pelanggan</p>
                            <p class="font-semibold">{{ salesOrder.customer_reference || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Syarat Pembayaran</p>
                            <p class="font-semibold">{{ salesOrder.payment_terms || '-' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Reservasi Stok</p>
                            <p class="font-semibold">
                                {{ salesOrder.reserve_stock ? 'Ya' : 'Tidak' }}
                                <span v-if="salesOrder.reserve_stock && salesOrder.reservation_applied_at" class="text-xs text-gray-500">
                                    • Diterapkan {{ new Date(salesOrder.reservation_applied_at).toLocaleString('id-ID') }}
                                </span>
                            </p>
                        </div>
                        <div v-if="salesOrder.notes">
                            <p class="text-gray-500">Catatan</p>
                            <p class="font-semibold whitespace-pre-line">{{ salesOrder.notes }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-auto border border-gray-200 rounded">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">SKU</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Deskripsi</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Qty</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Harga</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Pajak (%)</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Lokasi</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="line in salesOrder.lines" :key="line.id">
                                <td class="px-4 py-3">{{ line.variant?.sku }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold">{{ line.description }}</p>
                                    <p class="text-xs text-gray-500">Requested: {{ line.requested_delivery_date || '-' }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatNumber(line.quantity) }} {{ line.uom?.code }}
                                    <div class="text-xs text-gray-500">
                                        Terkirim: {{ formatNumber(line.quantity_delivered) }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Sisa: {{ formatNumber(Number(line.quantity) - Number(line.quantity_delivered)) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatNumber(line.unit_price) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatNumber(line.tax_rate) }}
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ line.reservation_location?.name || '-' }}</p>
                                    <p class="text-xs text-gray-500" v-if="line.quantity_reserved_base > 0">
                                        Tersedia: {{ formatNumber(line.quantity_reserved_base) }} (base)
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ formatNumber(line.line_total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <div class="border border-gray-200 rounded p-4 w-full md:w-1/2 lg:w-1/3 bg-gray-50 space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span>Subtotal</span>
                            <span>{{ formatNumber(amountSummary.subtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span>Total Pajak</span>
                            <span>{{ formatNumber(amountSummary.tax) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-base font-semibold">
                            <span>Grand Total</span>
                            <span>{{ formatNumber(amountSummary.total) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

