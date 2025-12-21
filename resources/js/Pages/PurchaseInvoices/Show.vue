<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    invoice: Object,
    filters: Object,
    primaryCurrency: Object,
    statusOptions: Object,
    paymentMethods: Array,
    canPost: Boolean,
    canEdit: Boolean,
    canDelete: Boolean,
});

const deleteForm = useForm({});
const actionForm = useForm({});
const showDeleteModal = ref(false);

const statusLabel = computed(() => props.statusOptions?.[props.invoice?.status] || props.invoice?.status);

const subtotal = computed(() =>
    props.invoice?.lines?.reduce((sum, line) => sum + Number(line.line_total || 0), 0) || 0
);

const taxTotal = computed(() =>
    props.invoice?.lines?.reduce((sum, line) => sum + Number(line.tax_amount || 0), 0) || 0
);

const totalAmount = computed(() => Number(props.invoice?.total_amount || subtotal.value + taxTotal.value));

const baseTotal = computed(() => totalAmount.value * Number(props.invoice?.exchange_rate || 1));

function deleteInvoice() {
    deleteForm.delete(route('purchase-invoices.destroy', props.invoice.id), {
        preserveScroll: true,
        onSuccess: () => (showDeleteModal.value = false),
    });
}

function postInvoice() {
    actionForm.post(route('purchase-invoices.post', props.invoice.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Faktur ${invoice?.invoice_number || ''}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Faktur Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <div class="flex items-center justify-between">
                        <AppBackLink :href="route('purchase-invoices.index', filters)" text="Kembali ke Daftar Faktur" />
                        <div class="flex items-center">
                            <AppPrimaryButton v-if="canPost" @click="postInvoice" :disabled="actionForm.processing">
                                Posting Faktur
                            </AppPrimaryButton>
                            <a :href="route('purchase-invoices.print', invoice.id)" target="_blank">
                                <AppPrintButton title="Print" />
                            </a>
                            <Link v-if="canEdit" :href="route('purchase-invoices.edit', invoice.id)">
                                <AppEditButton title="Edit" />
                            </Link>
                            <AppDeleteButton
                                v-if="canDelete"
                                title="Delete"
                                @click="showDeleteModal = true"
                            />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Nomor Faktur</span>
                                <span class="font-semibold text-gray-800">{{ invoice.invoice_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Status</span>
                                <span class="font-semibold text-main-600">{{ statusLabel }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tanggal Faktur</span>
                                <span>{{ invoice.invoice_date }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Jatuh Tempo</span>
                                <span>{{ invoice.due_date || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">No. Dokumen Vendor</span>
                                <span>{{ invoice.vendor_invoice_number || '-' }}</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Supplier</span>
                                <span class="font-medium">{{ invoice.partner?.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Cabang</span>
                                <span>{{ invoice.branch?.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">PO Referensi</span>
                                <span class="text-right">
                                    <template v-if="invoice.purchase_orders && invoice.purchase_orders.length">
                                        <span v-for="(po, idx) in invoice.purchase_orders" :key="po.id">
                                            <a :href="route('purchase-orders.show', po.id)" class="text-main-600 hover:underline">{{ po.order_number }}</a><span v-if="idx < invoice.purchase_orders.length - 1">, </span>
                                        </span>
                                    </template>
                                    <span v-else class="text-gray-400">Direct Invoice</span>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Metode Pembayaran</span>
                                <span>{{ invoice.payment_method || '-' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Rekening Supplier</span>
                                <span class="text-right">
                                    <template v-if="invoice.bank_account">
                                        {{ invoice.bank_account.bank_name }} - {{ invoice.bank_account.account_number }}
                                    </template>
                                    <span v-else>-</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                            <div>
                                <p class="text-gray-500 text-sm">Nilai Faktur</p>
                                <p class="text-2xl font-bold text-gray-800">
                                    {{ invoice.currency?.symbol }} {{ formatNumber(totalAmount) }}
                                </p>
                            </div>
                            <div class="text-sm text-gray-600 space-y-1 text-right">
                                <p>Subtotal: {{ formatNumber(subtotal) }}</p>
                                <p>Pajak: {{ formatNumber(taxTotal) }}</p>
                                <p>Total: {{ formatNumber(totalAmount) }}</p>
                                <p>
                                    Konversi:
                                    {{ primaryCurrency?.symbol }} {{ formatNumber(baseTotal) }}
                                </p>
                                <p v-if="invoice.ppv_amount">PPV: {{ primaryCurrency?.symbol }} {{ formatNumber(invoice.ppv_amount) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200 text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border border-gray-200 px-3 py-2 text-left w-32">Referensi</th>
                                    <th class="border border-gray-200 px-3 py-2 text-left">Deskripsi</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right w-24">Qty</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right w-32">Harga</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right w-32">Pajak</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right w-32">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in invoice.lines" :key="line.id" class="border-b border-gray-100">
                                    <td class="px-3 py-2 border border-gray-100 align-top text-xs text-gray-500">
                                        <div v-if="line.purchase_order_line">PO: {{ line.purchase_order_line.purchase_order?.order_number }}</div>
                                        <div v-if="line.goods_receipt_line">GRN: {{ line.goods_receipt_line.goods_receipt?.receipt_number }}</div>
                                        <div v-if="!line.purchase_order_line && !line.goods_receipt_line">-</div>
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 align-top">
                                        <div v-if="line.product_variant" class="font-medium text-gray-800">
                                            {{ line.product_variant.product?.name }} - {{ line.product_variant.sku }}
                                        </div>
                                        <p :class="{'text-gray-500 text-xs': line.product_variant, 'font-medium text-gray-800': !line.product_variant}">
                                            {{ line.description }}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-0.5">UOM: {{ line.uom?.code }}</p>
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right align-top">
                                        {{ formatNumber(line.quantity) }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right align-top">
                                        {{ formatNumber(line.unit_price) }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right align-top">
                                        {{ formatNumber(line.tax_amount) }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right align-top font-medium">
                                        {{ formatNumber(line.line_total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <p class="text-sm text-gray-500 font-semibold">Catatan</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">
                            {{ invoice.notes || '-' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Hapus Faktur"
            @close="showDeleteModal = false"
            @confirm="deleteInvoice"
        />
    </AuthenticatedLayout>
</template>

