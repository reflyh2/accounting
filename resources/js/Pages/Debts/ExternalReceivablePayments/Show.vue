<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    item: Object,
    filters: Object,
    paymentMethodOptions: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteItem = () => {
    form.delete(route('external-receivable-payments.destroy', props.item.id), {
        onSuccess: () => { showDeleteConfirmation.value = false; },
        onError: () => { showDeleteConfirmation.value = false; }
    });
};
</script>

<template>
    <Head title="Detail Penerimaan Piutang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Penerimaan Piutang</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('external-receivable-payments.index', filters)" text="Kembali ke Penerimaan Piutang" />
                        </div>

                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Dokumen #{{ item.number }}</h3>
                            <div class="flex items-center">
                                <a :href="route('external-receivable-payments.print', item.id)" target="_blank" class="mr-1">
                                    <AppPrintButton title="Print" />
                                </a>
                                <a :href="route('external-receivable-payments.edit', item.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </a>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ item.branch?.branch_group?.company?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Partner:</p>
                                <p>{{ item.partner?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ item.branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ new Date(item.payment_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Akun:</p>
                                <p>{{ item.account?.code }} {{ item.account?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mata Uang:</p>
                                <p>{{ item.currency?.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jumlah:</p>
                                <p>{{ item.currency?.symbol }} {{ formatNumber(item.amount) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Metode:</p>
                                <p>{{ paymentMethodOptions[item.payment_method] || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Referensi:</p>
                                <p>{{ item.reference_number || '-' }}</p>
                            </div>
                            <div v-if="item.payment_method === 'transfer'">
                                <p class="font-semibold">Rekening Partner:</p>
                                <p>{{ item.partner_bank_account?.display_name || '-' }}</p>
                            </div>
                            <div v-if="item.payment_method === 'cek' || item.payment_method === 'giro'">
                                <p class="font-semibold">Tanggal Cek/Giro:</p>
                                <p>{{ item.instrument_date ? new Date(item.instrument_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div v-if="item.payment_method === 'cek' || item.payment_method === 'giro'">
                                <p class="font-semibold">Tanggal Pencairan:</p>
                                <p>{{ item.withdrawal_date ? new Date(item.withdrawal_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ item.notes || '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Detail Penerimaan</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left"># Piutang</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Tanggal Terbit</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right">Diterima</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="d in item.details" :key="d.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ d.external_debt?.number }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ new Date(d.external_debt?.issue_date).toLocaleDateString('id-ID') }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ item.currency?.symbol }} {{ formatNumber(d.amount) }}</td>
                                    </tr>
                                    <tr v-if="!item.details || item.details.length === 0">
                                        <td colspan="3" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada detail.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Penerimaan Piutang"
            message="Apakah Anda yakin ingin menghapus penerimaan piutang ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteItem"
        />
    </AuthenticatedLayout>
</template>


