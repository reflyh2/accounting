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
    cashPaymentJournal: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const kasBankEntry = props.cashPaymentJournal?.journal_entries?.find(entry => entry.credit > 0);
const otherEntries = props.cashPaymentJournal?.journal_entries?.filter(entry => entry.credit == 0);

const deleteCashPaymentJournal = () => {
    form.delete(route('cash-payment-journals.destroy', props.cashPaymentJournal.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Pengeluaran Kas" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pengeluaran Kas</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('cash-payment-journals.index', filters)" text="Kembali ke Daftar Pengeluaran Kas" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ cashPaymentJournal.journal_number }}</h3>
                            <div class="flex items-center">
                                <a :href="route('cash-payment-journals.print', cashPaymentJournal.id)" target="_blank">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link :href="route('cash-payment-journals.edit', cashPaymentJournal.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ cashPaymentJournal.date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ cashPaymentJournal.branch.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ cashPaymentJournal.description }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Keluar dari Akun:</p>
                                <p>{{ kasBankEntry.account ? `${kasBankEntry.account.code} - ${kasBankEntry.account.name}` : '-' }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Entri Jurnal</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-300 px-4 py-2">No. Akun</th>
                                        <th class="border border-gray-300 px-4 py-2">Nama Akun</th>
                                        <th class="border border-gray-300 px-4 py-2">Jumlah</th>
                                        <th class="border border-gray-300 px-4 py-2">Mata Uang</th>
                                        <th class="border border-gray-300 px-4 py-2">Kurs</th>
                                        <th class="border border-gray-300 px-4 py-2">Jumlah Mata Uang Utama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="entry in otherEntries" :key="entry.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.account.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.account.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.debit) }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.currency.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.exchange_rate) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.primary_currency_credit) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="5" class="border border-gray-300 px-4 py-2 text-left">Total</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(cashPaymentJournal.journal_entries.reduce((sum, entry) => sum + Number(entry.primary_currency_debit), 0)) }}</th>
                                    </tr>
                                    <tr>
                                        <th colspan="2" class="border border-gray-300 px-4 py-2 text-left">Total Pengeluaran dari {{ kasBankEntry.account.name }}</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(kasBankEntry.credit) }}</th>
                                        <th class="border border-gray-300 px-4 py-2 text-left">{{ kasBankEntry.currency.code }}</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(kasBankEntry.exchange_rate) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <DeleteConfirmationModal
        v-model:show="showDeleteConfirmation"
        title="Hapus Jurnal Pengeluaran Kas"
        content="Apakah Anda yakin ingin menghapus jurnal penerimaan kas ini?"
        @confirm="deleteCashPaymentJournal"
    />
</template>