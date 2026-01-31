<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
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
</script>

<template>
    <Head title="Detail Faktur Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Faktur Penjualan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-invoices.index', filters)" text="Kembali ke Daftar Faktur Penjualan" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ invoice.invoice_number }}</h3>
                            <div class="flex items-center">
                              <Link v-if="canPost" :href="route('sales-invoices.post', invoice.id)" method="post" as="button">
                                 <AppPrimaryButton>Posting Faktur</AppPrimaryButton>
                              </Link>
                              <a :href="route('sales-invoices.print', invoice.id)" target="_blank">
                                 <AppPrintButton title="Print" />
                              </a>
                              <Link v-if="canEdit" :href="route('sales-invoices.edit', invoice.id)">
                                 <AppEditButton title="Edit" />
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
                                <p class="font-semibold">Sales Order(s):</p>
                                <div v-if="invoice.is_direct_invoice" class="mt-1">
                                    <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">Direct Invoice</span>
                                </div>
                                <div v-else class="flex flex-wrap gap-2 mt-1">
                                    <Link v-for="so in invoice.sales_orders" :key="so.id" :href="route('sales-orders.show', so.id)" class="bg-blue-100 text-blue-600 hover:bg-blue-200 text-sm px-2 py-0.5 rounded">{{ so.order_number }}</Link>
                                </div>
                            </div>
                            <div>
                                <p class="font-semibold">Customer:</p>
                                <p>{{ invoice.partner?.name }}</p>
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
                            <div v-if="invoice.tax_invoice_code_label">
                                <p class="font-semibold">Kode Faktur Pajak:</p>
                                <p>{{ invoice.tax_invoice_code_label }}</p>
                            </div>
                            <div v-if="invoice.payment_method_label">
                                <p class="font-semibold">Metode Pembayaran:</p>
                                <p>{{ invoice.payment_method_label }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Detail Faktur</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">No</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Deskripsi</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Satuan</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Qty</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Harga</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Diskon (%)</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Pajak (%)</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Total</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2" v-if="hasOtherCurrency">Total ({{ page.props.primaryCurrency.code }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in invoice.lines" :key="line.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2 text-center">{{ line.line_number }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.description }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.uom_label }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.unit_price) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.discount_rate) }}%</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.tax_rate) }}%</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.line_total) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">{{ formatNumber(line.line_total_base) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="7" class="border border-gray-300 px-4 py-2 font-semibold text-right">Subtotal</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(invoice.subtotal) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">-</td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="border border-gray-300 px-4 py-2 font-semibold text-right">Total Pajak</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(invoice.tax_total) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">-</td>
                                    </tr>
                                    <tr v-if="invoice.shipping_charge && invoice.shipping_charge > 0">
                                        <td colspan="7" class="border border-gray-300 px-4 py-2 font-semibold text-right">Biaya Kirim</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(invoice.shipping_charge) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">-</td>
                                    </tr>
                                    <tr>
                                        <td colspan="7" class="border border-gray-300 px-4 py-2 font-semibold text-right">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold" :colspan="hasOtherCurrency ? 2 : 1">{{ formatNumber(invoice.total_amount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-if="invoice.costs && invoice.costs.length > 0" class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Biaya Tambahan</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Biaya</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Catatan</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cost in invoice.costs" :key="cost.id">
                                        <td class="border border-gray-300 px-4 py-2">
                                            <span v-if="cost.cost_item">
                                                {{ cost.cost_item.code }} - {{ cost.cost_item.name }}
                                            </span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2">{{ cost.description || '—' }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(cost.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="invoice.notes" class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Catatan</h4>
                            <p class="text-gray-700 whitespace-pre-line">{{ invoice.notes }}</p>
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
