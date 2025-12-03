<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const hasOtherCurrency = computed(() => {
    return props.invoice?.lines?.some(line => line.currency_id != page.props.primaryCurrency.id);
});

const props = defineProps({
    invoice: Object,
    filters: Object,
    primaryCurrency: Object,
    statusOptions: Object,
    canPost: Boolean,
    canEdit: Boolean,
    canDelete: Boolean,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteInvoice = () => {
    form.delete(route('sales-invoices.destroy', props.invoice.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const postInvoice = () => {
    form.post(route('sales-invoices.post', props.invoice.id));
};
</script>

<template>
    <Head title="Detail Faktur Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Faktur Penjualan</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-invoices.index', filters)" text="Kembali ke Daftar Faktur Penjualan" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ invoice.invoice_number }}</h3>
                            <div class="flex items-center">
                              <a :href="route('sales-invoices.print', invoice.id)" target="_blank">
                                 <AppPrintButton title="Print" />
                              </a>
                              <Link v-if="canEdit" :href="route('sales-invoices.edit', invoice.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <Link v-if="canPost" :href="route('sales-invoices.post', invoice.id)" method="post" as="button">
                                 <AppEditButton title="Post" />
                              </Link>
                              <AppDeleteButton v-if="canDelete" @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Tanggal Faktur:</p>
                                <p>{{ invoice.invoice_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Jatuh Tempo:</p>
                                <p>{{ invoice.due_date || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nomor SO:</p>
                                <p>{{ invoice.sales_order?.order_number }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Customer:</p>
                                <p>{{ invoice.sales_order?.partner?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ invoice.sales_order?.branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ statusOptions[invoice.status] }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mata Uang:</p>
                                <p>{{ invoice.currency?.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kurs:</p>
                                <p>{{ formatNumber(invoice.exchange_rate) }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Detail Faktur</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">No. SO Line</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Deskripsi</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Qty</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Harga</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Total</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Pajak</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2" v-if="hasOtherCurrency">Total ({{ page.props.primaryCurrency.code }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in invoice.lines" :key="line.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2">{{ line.line_number }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.description }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.unit_price) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.line_total) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.tax_amount) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">{{ formatNumber(line.line_total_base) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="border border-gray-300 px-4 py-2 font-semibold text-right">Subtotal</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(invoice.subtotal) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(invoice.tax_total) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold" v-if="hasOtherCurrency">-</td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="border border-gray-300 px-4 py-2 font-semibold text-right">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold" :colspan="hasOtherCurrency ? 2 : 1">{{ formatNumber(invoice.total_amount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Faktur Penjualan"
            @close="showDeleteConfirmation = false"
            @confirm="deleteInvoice"
        />
    </AuthenticatedLayout>
</template>
