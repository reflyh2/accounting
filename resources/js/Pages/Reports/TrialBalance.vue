<script setup>
import { ref } from 'vue';
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
    filters: Object,
    trialBalanceData: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('trial-balance.index'), form.value, {
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
</script>

<template>
    <Head title="Neraca Saldo" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Neraca Saldo</h2>
        </template>

        <div class="mx-auto">
            <AccountingReportTabs activeTab="trial-balance.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <!-- Filters -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <AppSelect
                            v-model="form.company_id"
                            :options="companies.map(c => ({ value: c.id, label: c.name }))"
                            label="Perusahaan"
                            multiple
                            placeholder="Pilih perusahaan"
                        />
                        <AppSelect
                            v-model="form.branch_id"
                            :options="branches.map(b => ({ value: b.id, label: b.name }))"
                            label="Cabang"
                            multiple
                            placeholder="Pilih cabang"
                        />
                        <div class="grid grid-cols-2 gap-4">
                            <AppInput v-model="form.start_date" type="date" label="Dari Tanggal" />
                            <AppInput v-model="form.end_date" type="date" label="Sampai Tanggal" />
                        </div>
                    </div>

                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <!-- Trial Balance Table -->
                    <template v-if="trialBalanceData?.rows?.length">
                        <ReportTable>
                            <thead>
                                <tr class="bg-gray-100">
                                    <ReportTH sticky>Kode</ReportTH>
                                    <ReportTH sticky>Nama Akun</ReportTH>
                                    <ReportTH sticky class="text-right">Debit</ReportTH>
                                    <ReportTH sticky class="text-right">Kredit</ReportTH>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in trialBalanceData.rows" :key="row.account_code" class="group hover:bg-gray-50">
                                    <ReportTD class="font-mono">{{ row.account_code }}</ReportTD>
                                    <ReportTD>{{ row.account_name }}</ReportTD>
                                    <ReportTD class="text-right">{{ row.debit > 0 ? formatCurrency(row.debit) : '-' }}</ReportTD>
                                    <ReportTD class="text-right">{{ row.credit > 0 ? formatCurrency(row.credit) : '-' }}</ReportTD>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="group bg-gray-100 font-bold">
                                    <ReportTD :colspan="2" class="text-right">Total:</ReportTD>
                                    <ReportTD class="text-right">{{ formatCurrency(trialBalanceData.totals.debit) }}</ReportTD>
                                    <ReportTD class="text-right">{{ formatCurrency(trialBalanceData.totals.credit) }}</ReportTD>
                                </tr>
                            </tfoot>
                        </ReportTable>
                    </template>

                    <div v-else class="text-center py-12 text-gray-500">
                        <p>Pilih filter dan klik "Tampilkan Laporan" untuk melihat neraca saldo.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
