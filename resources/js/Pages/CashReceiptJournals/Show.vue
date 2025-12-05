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
    return props.cashReceiptJournal?.journal_entries?.some(entry => entry.currency_id != page.props.primaryCurrency.id);
});

const props = defineProps({
    cashReceiptJournal: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);
const kasBankEntry = props.cashReceiptJournal?.journal_entries?.find(entry => entry.debit > 0);
const otherEntries = props.cashReceiptJournal?.journal_entries?.filter(entry => entry.debit == 0);

const deleteCashReceiptJournal = () => {
    form.delete(route('cash-receipt-journals.destroy', props.cashReceiptJournal.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Penerimaan Kas" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Penerimaan Kas</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('cash-receipt-journals.index', filters)" text="Kembali ke Daftar Penerimaan Kas" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ cashReceiptJournal.journal_number }}</h3>
                            <div class="flex items-center">
                                <a :href="route('cash-receipt-journals.print', cashReceiptJournal.id)" target="_blank">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link :href="route('cash-receipt-journals.edit', cashReceiptJournal.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ cashReceiptJournal.date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ cashReceiptJournal.branch.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ cashReceiptJournal.description }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Terima ke Akunk:</p>
                                <p>{{ kasBankEntry.account ? `${kasBankEntry.account.code} - ${kasBankEntry.account.name}` : '-' }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Entri Jurnal</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">No. Akun</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Nama Akun</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2" v-if="hasOtherCurrency">Mata Uang</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Jumlah</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2" v-if="hasOtherCurrency">Kurs</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2" v-if="hasOtherCurrency">Jumlah ({{ page.props.primaryCurrency.code }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="entry in otherEntries" :key="entry.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.account.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.account.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2" v-if="hasOtherCurrency">{{ entry.currency.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.credit) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">{{ formatNumber(entry.exchange_rate) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">{{ formatNumber(entry.primary_currency_credit) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="border border-gray-300 px-4 py-2 text-left">Total Penerimaan ke {{ kasBankEntry.account.name }}</th>
                                        <th class="border border-gray-300 px-4 py-2 text-left" v-if="hasOtherCurrency">{{ kasBankEntry.currency.code }}</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(kasBankEntry.debit) }}</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">{{ formatNumber(kasBankEntry.exchange_rate) }}</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right" v-if="hasOtherCurrency">{{ formatNumber(kasBankEntry.primary_currency_debit) }}</th>
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
        title="Hapus Jurnal Penerimaan Kas"
        content="Apakah Anda yakin ingin menghapus jurnal penerimaan kas ini?"
        @confirm="deleteCashReceiptJournal"
    />
</template>