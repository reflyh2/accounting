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
    journal: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteJournal = () => {
    form.delete(route('journals.destroy', props.journal.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Jurnal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Jurnal</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('journals.index', filters)" text="Kembali ke Daftar Jurnal" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ journal.journal_number }}</h3>
                            <div class="flex items-center">                              
                              <a :href="route('journals.print', journal.id)" target="_blank">
                                 <AppPrintButton title="Print" />
                              </a>
                              <Link :href="route('journals.edit', journal.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ journal.date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ journal.branch.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ journal.description }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Entri Jurnal</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-300 px-4 py-2">No. Akun</th>
                                        <th class="border border-gray-300 px-4 py-2">Nama Akun</th>
                                        <th class="border border-gray-300 px-4 py-2">Debet</th>
                                        <th class="border border-gray-300 px-4 py-2">Kredit</th>
                                        <th class="border border-gray-300 px-4 py-2">Mata Uang</th>
                                        <th class="border border-gray-300 px-4 py-2">Kurs</th>
                                        <th class="border border-gray-300 px-4 py-2">Debit Mata Uang Utama</th>
                                        <th class="border border-gray-300 px-4 py-2">Kredit Mata Uang Utama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="entry in journal.journal_entries" :key="entry.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.account.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.account.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.debit) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.credit) }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ entry.currency.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.exchange_rate) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.primary_currency_debit) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(entry.primary_currency_credit) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="border border-gray-300 px-4 py-2 font-semibold">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(journal.journal_entries.reduce((total, entry) => total + Number(entry.debit), 0)) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(journal.journal_entries.reduce((total, entry) => total + Number(entry.credit), 0)) }}</td>
                                        <td></td>
                                        <td></td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(journal.journal_entries.reduce((total, entry) => total + Number(entry.primary_currency_debit), 0)) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(journal.journal_entries.reduce((total, entry) => total + Number(entry.primary_currency_credit), 0)) }}</td>
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
            title="Hapus Jurnal"
            @close="showDeleteConfirmation = false"
            @confirm="deleteJournal"
        />
    </AuthenticatedLayout>
</template>