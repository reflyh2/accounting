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
    agreement: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAgreement = () => {
    form.delete(route('asset-financing-agreements.destroy', props.agreement.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Perjanjian Pembiayaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Perjanjian Pembiayaan Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-financing-agreements.index', filters)" text="Kembali ke Daftar Perjanjian Pembiayaan Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ agreement.number }}</h3>
                            <div class="flex items-center">                              
                              <a :href="route('asset-financing-agreements.print', agreement.id)" target="_blank">
                                 <AppPrintButton title="Print" />
                              </a>
                              <Link :href="route('asset-financing-agreements.edit', agreement.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ agreement.branch.branch_group.company.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ agreement.branch.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Perjanjian:</p>
                                <p>{{ new Date(agreement.agreement_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kreditor:</p>
                                <p>{{ agreement.creditor.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Jumlah:</p>
                                <p>{{ formatNumber(agreement.total_amount) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Bunga Tahunan:</p>
                                <p>{{ agreement.interest_rate }}%</p>
                            </div>
                            <div>
                                <p class="font-semibold">Metode Perhitungan Bunga:</p>
                                <p>{{ agreement.interest_calculation_method_label }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Mulai:</p>
                                <p>{{ new Date(agreement.start_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Selesai:</p>
                                <p>{{ new Date(agreement.end_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Frekuensi Pembayaran:</p>
                                <p>{{ agreement.payment_frequency_label }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ agreement.status_label }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ agreement.notes || '-' }}</p>
                            </div>
                        </div>

                        <!-- Asset Invoice Details -->
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Detail Invoice Aset</h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="font-semibold">Nomor Invoice:</p>
                                        <p>{{ agreement.asset_invoice.invoice_number }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold">Tanggal Invoice:</p>
                                        <p>{{ new Date(agreement.asset_invoice.invoice_date).toLocaleDateString('id-ID') }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold">Aset:</p>
                                        <p>{{ agreement.asset_invoice.asset_invoice_details?.[0]?.asset?.name || 'No Asset' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold">Vendor:</p>
                                        <p>{{ agreement.asset_invoice.partner.name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Creation/Update Info -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <div class="grid grid-cols-2 gap-4 text-xs text-gray-500">
                                <div v-if="agreement.created_by">
                                    <p class="font-semibold">Dibuat oleh:</p>
                                    <p>{{ agreement.created_by?.name }} - {{ new Date(agreement.created_at).toLocaleDateString('id-ID') }}</p>
                                </div>
                                <div v-if="agreement.updated_by">
                                    <p class="font-semibold">Diubah oleh:</p>
                                    <p>{{ agreement.updated_by?.name }} - {{ new Date(agreement.updated_at).toLocaleDateString('id-ID') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Perjanjian Pembiayaan Aset"
            @close="showDeleteConfirmation = false"
            @confirm="deleteAgreement"
        />
    </AuthenticatedLayout>
</template> 