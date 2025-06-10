<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    payment: Object,
    filters: Object,
    paymentMethods: Object,
    paymentTypes: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deletePayment = () => {
    form.delete(route('asset-invoice-payments.destroy', props.payment.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Pembayaran Faktur Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pembayaran Faktur Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-invoice-payments.index', filters)" text="Kembali ke Daftar Pembayaran" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ payment.number }}</h3>
                            <div class="flex items-center">                              
                                <a :href="route('asset-invoice-payments.print', payment.id)" target="_blank">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link :href="route('asset-invoice-payments.edit', payment.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal Bayar:</p>
                                <p>{{ new Date(payment.payment_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tipe:</p>
                                <p>{{ paymentTypes[payment.type] || payment.type }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Partner:</p>
                                <p>{{ payment.partner.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Referensi:</p>
                                <p>{{ payment.reference || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jumlah:</p>
                                <p>{{ formatNumber(payment.amount) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Metode Pembayaran:</p>
                                <p>{{ paymentMethods[payment.payment_method] || payment.payment_method }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ payment.notes || '-' }}</p>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Alokasi Pembayaran</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-300 px-4 py-2">Nomor Faktur</th>
                                        <th class="border border-gray-300 px-4 py-2">Tanggal Faktur</th>
                                        <th class="border border-gray-300 px-4 py-2">Cabang</th>
                                        <th class="border border-gray-300 px-4 py-2">Total Faktur</th>
                                        <th class="border border-gray-300 px-4 py-2">Jumlah Dibayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="allocation in payment.allocations" :key="allocation.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ allocation.asset_invoice.number }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ new Date(allocation.asset_invoice.invoice_date).toLocaleDateString('id-ID') }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ allocation.asset_invoice.branch?.name || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(allocation.asset_invoice.total_amount) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(allocation.allocated_amount) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="border border-gray-300 px-4 py-2 font-semibold">Total Dibayar</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(payment.amount) }}</td>
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
            title="Hapus Pembayaran"
            @close="showDeleteConfirmation = false"
            @confirm="deletePayment"
        />
    </AuthenticatedLayout>
</template> 