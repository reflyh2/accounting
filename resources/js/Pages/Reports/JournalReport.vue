<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AccountingReportTabs from '@/Tabs/AccountingReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import ReportTable from '@/Components/ReportTable.vue';
import ReportTH from '@/Components/ReportTH.vue';
import ReportTD from '@/Components/ReportTD.vue';

const props = defineProps({
    companies: Array,
    branches: Array,
    journalTypes: Array,
    filters: Object,
    journalData: Array,
    typeLabels: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    journal_type: props.filters.journal_type || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('journal-report.index'), form.value, {
        preserveState: false,
        preserveScroll: true,
    });
}

function formatCurrency(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(number ?? 0);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
}

function getTypeLabel(type) {
    return props.typeLabels?.[type] || type;
}

const grandTotals = computed(() => {
    if (!props.journalData?.length) return { debit: 0, credit: 0 };
    let debit = 0;
    let credit = 0;
    for (const journal of props.journalData) {
        for (const entry of journal.journal_entries || []) {
            debit += parseFloat(entry.primary_currency_debit) || 0;
            credit += parseFloat(entry.primary_currency_credit) || 0;
        }
    }
    return { debit, credit };
});
</script>

<template>
    <Head title="Laporan Jurnal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Laporan Jurnal</h2>
        </template>

        <div class="mx-auto">
            <AccountingReportTabs activeTab="journal-report.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <!-- Filters -->
                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <AppSelect
                            v-model="form.company_id"
                            :options="companies.map(c => ({ value: c.id, label: c.name }))"
                            label="Perusahaan"
                            multiple
                            placeholder="Semua Perusahaan"
                        />
                        <AppSelect
                            v-model="form.branch_id"
                            :options="branches.map(b => ({ value: b.id, label: b.name }))"
                            label="Cabang"
                            multiple
                            placeholder="Semua Cabang"
                        />
                        <AppSelect
                            v-model="form.journal_type"
                            :options="[{ value: '', label: 'Semua Tipe' }, ...journalTypes]"
                            label="Tipe Jurnal"
                            placeholder="Semua Tipe"
                        />
                        <div></div>
                        <AppInput v-model="form.start_date" type="date" label="Dari Tanggal" />
                        <AppInput v-model="form.end_date" type="date" label="Sampai Tanggal" />
                    </div>

                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <!-- Journal Table -->
                    <template v-if="journalData?.length">
                        <ReportTable>
                            <thead>
                                <tr class="bg-gray-100">
                                    <ReportTH sticky>Tanggal</ReportTH>
                                    <ReportTH sticky>No. Jurnal</ReportTH>
                                    <ReportTH sticky>Tipe</ReportTH>
                                    <ReportTH sticky>Keterangan</ReportTH>
                                    <ReportTH sticky>Kode Akun</ReportTH>
                                    <ReportTH sticky>Nama Akun</ReportTH>
                                    <ReportTH sticky class="text-right">Debit</ReportTH>
                                    <ReportTH sticky class="text-right">Kredit</ReportTH>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="journal in journalData" :key="journal.id">
                                    <tr v-for="(entry, idx) in journal.journal_entries" :key="entry.id" class="group hover:bg-gray-50">
                                        <!-- Show journal info only on first entry row -->
                                        <ReportTD v-if="idx === 0" :rowspan="journal.journal_entries.length" class="align-top border-b">
                                            {{ formatDate(journal.date) }}
                                        </ReportTD>
                                        <ReportTD v-if="idx === 0" :rowspan="journal.journal_entries.length" class="align-top font-medium border-b">
                                            {{ journal.journal_number }}
                                        </ReportTD>
                                        <ReportTD v-if="idx === 0" :rowspan="journal.journal_entries.length" class="align-top border-b">
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 whitespace-nowrap">
                                                {{ getTypeLabel(journal.journal_type) }}
                                            </span>
                                        </ReportTD>
                                        <ReportTD v-if="idx === 0" :rowspan="journal.journal_entries.length" class="align-top text-gray-600 border-b">
                                            {{ journal.description || '-' }}
                                        </ReportTD>
                                        <ReportTD class="font-mono">{{ entry.account?.code }}</ReportTD>
                                        <ReportTD :class="{ 'pl-8': entry.primary_currency_credit > 0 }">{{ entry.account?.name }}</ReportTD>
                                        <ReportTD class="text-right">{{ entry.primary_currency_debit > 0 ? formatCurrency(entry.primary_currency_debit) : '-' }}</ReportTD>
                                        <ReportTD class="text-right">{{ entry.primary_currency_credit > 0 ? formatCurrency(entry.primary_currency_credit) : '-' }}</ReportTD>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr class="group bg-gray-100 font-bold">
                                    <ReportTD :colspan="6" class="text-right">Grand Total:</ReportTD>
                                    <ReportTD class="text-right">{{ formatCurrency(grandTotals.debit) }}</ReportTD>
                                    <ReportTD class="text-right">{{ formatCurrency(grandTotals.credit) }}</ReportTD>
                                </tr>
                            </tfoot>
                        </ReportTable>
                    </template>

                    <div v-else class="text-center py-12 text-gray-500">
                        <p>Pilih filter dan klik "Tampilkan Laporan" untuk melihat jurnal.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
