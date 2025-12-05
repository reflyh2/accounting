<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
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
                        <div class="flex gap-2">
                            <AppPrimaryButton v-if="canPost" @click="postInvoice" :disabled="actionForm.processing">
                                Posting Faktur
                            </AppPrimaryButton>
                            <Link v-if="canEdit" :href="route('purchase-invoices.edit', invoice.id)">
                                <AppEditButton title="Edit" />
                            </Link>
                            <AppDeleteButton
                                v-if="canDelete"
                                title="Hapus"
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
                                <span class="text-gray-500">Tanggal</span>
                                <span>{{ invoice.invoice_date }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Jatuh Tempo</span>
                                <span>{{ invoice.due_date || '-' }}</span>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Supplier</span>
                                <span>{{ invoice.purchase_order?.partner?.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Cabang</span>
                                <span>{{ invoice.purchase_order?.branch?.name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">PO Referensi</span>
                                <span>{{ invoice.purchase_order?.order_number }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Mata Uang</span>
                                <span>{{ invoice.currency?.code }}</span>
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
                                <p>PPV: {{ primaryCurrency?.symbol }} {{ formatNumber(invoice.ppv_amount || 0) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200 text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border border-gray-200 px-3 py-2 text-left">GRN</th>
                                    <th class="border border-gray-200 px-3 py-2 text-left">Deskripsi</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right">Qty</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right">Harga</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right">Pajak</th>
                                    <th class="border border-gray-200 px-3 py-2 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in invoice.lines" :key="line.id" class="border-b border-gray-100">
                                    <td class="px-3 py-2 border border-gray-100">
                                        {{ line.goods_receipt_line?.goods_receipt?.receipt_number || '-' }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100">
                                        <p class="font-medium text-gray-800">{{ line.description }}</p>
                                        <p class="text-xs text-gray-500">UOM: {{ line.uom_label }}</p>
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right">
                                        {{ formatNumber(line.quantity) }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right">
                                        {{ formatNumber(line.unit_price) }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right">
                                        {{ formatNumber(line.tax_amount) }}
                                    </td>
                                    <td class="px-3 py-2 border border-gray-100 text-right">
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

