<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    payment: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deletePayment = () => {
    form.delete(route('asset-financing-payments.destroy', props.payment.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Pembayaran Pembiayaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pembayaran Pembiayaan Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-financing-payments.index')" text="Kembali ke Daftar Pembayaran" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ payment.number }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('asset-financing-payments.edit', payment.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ payment.payment_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ payment.branch.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kreditor:</p>
                                <p>{{ payment.creditor.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Referensi:</p>
                                <p>{{ payment.reference }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Metode Pembayaran:</p>
                                <p>{{ payment.payment_method }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ payment.notes }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Pokok:</p>
                                <p>{{ formatNumber(payment.principal_amount) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Bunga:</p>
                                <p>{{ formatNumber(payment.interest_amount) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Pembayaran:</p>
                                <p>{{ formatNumber(payment.total_paid_amount) }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Alokasi Pembayaran</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-300 px-4 py-2">Perjanjian Pembiayaan</th>
                                        <th class="border border-gray-300 px-4 py-2">Aset</th>
                                        <th class="border border-gray-300 px-4 py-2">Jumlah Dialokasikan</th>
                                        <th class="border border-gray-300 px-4 py-2">Pokok</th>
                                        <th class="border border-gray-300 px-4 py-2">Bunga</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="allocation in payment.allocations" :key="allocation.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ allocation.asset_financing_agreement.number }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ allocation.asset_financing_agreement.asset_invoice.assets[0] ? allocation.asset_financing_agreement.asset_invoice.assets[0].name : '' }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(allocation.allocated_amount) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(allocation.principal_amount) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(allocation.interest_amount) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="border border-gray-300 px-4 py-2 font-semibold">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(payment.total_paid_amount) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(payment.principal_amount) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(payment.interest_amount) }}</td>
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