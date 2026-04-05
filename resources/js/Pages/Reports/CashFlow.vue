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
    cashFlowData: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('cash-flow.index'), form.value, {
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

function amountClass(amount) {
    if (amount > 0) return 'text-green-700';
    if (amount < 0) return 'text-red-700';
    return 'text-gray-500';
}
</script>

<template>
    <Head title="Laporan Arus Kas" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Laporan Arus Kas</h2>
        </template>

        <div class="mx-auto">
            <AccountingReportTabs activeTab="cash-flow.index" />

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

                    <!-- Cash Flow Report -->
                    <template v-if="cashFlowData">
                        <ReportTable>
                            <thead>
                                <tr class="bg-gray-100">
                                    <ReportTH sticky>Keterangan</ReportTH>
                                    <ReportTH sticky class="text-right w-56">Jumlah</ReportTH>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Opening Cash -->
                                <tr class="group bg-blue-50 font-semibold">
                                    <ReportTD>Saldo Kas Awal</ReportTD>
                                    <ReportTD class="text-right">{{ formatCurrency(cashFlowData.opening_cash) }}</ReportTD>
                                </tr>

                                <!-- Sections -->
                                <template v-for="(section, key) in cashFlowData.sections" :key="key">
                                    <!-- Section Header -->
                                    <tr class="group bg-gray-50 font-semibold">
                                        <ReportTD colspan="2">{{ section.label }}</ReportTD>
                                    </tr>

                                    <!-- Section Items -->
                                    <tr v-for="item in section.items" :key="item.account_code" class="group hover:bg-gray-50">
                                        <ReportTD class="pl-8">
                                            <span class="font-mono text-gray-400 mr-2">{{ item.account_code }}</span>
                                            {{ item.account_name }}
                                        </ReportTD>
                                        <ReportTD class="text-right" :class="amountClass(item.amount)">
                                            {{ formatCurrency(item.amount) }}
                                        </ReportTD>
                                    </tr>

                                    <tr v-if="!section.items.length" class="group">
                                        <ReportTD colspan="2" class="text-center text-gray-400 pl-8">Tidak ada aktivitas</ReportTD>
                                    </tr>

                                    <!-- Section Total -->
                                    <tr class="group bg-gray-50 font-semibold">
                                        <ReportTD class="text-right">Total {{ section.label }}:</ReportTD>
                                        <ReportTD class="text-right" :class="amountClass(section.total)">
                                            {{ formatCurrency(section.total) }}
                                        </ReportTD>
                                    </tr>
                                </template>

                                <!-- Net Cash Change -->
                                <tr class="group bg-gray-100 font-bold">
                                    <ReportTD>Kenaikan / (Penurunan) Kas Bersih</ReportTD>
                                    <ReportTD class="text-right" :class="amountClass(cashFlowData.net_cash_change)">
                                        {{ formatCurrency(cashFlowData.net_cash_change) }}
                                    </ReportTD>
                                </tr>

                                <!-- Closing Cash -->
                                <tr class="group bg-green-50 font-bold">
                                    <ReportTD>Saldo Kas Akhir</ReportTD>
                                    <ReportTD class="text-right">{{ formatCurrency(cashFlowData.closing_cash) }}</ReportTD>
                                </tr>
                            </tbody>
                        </ReportTable>
                    </template>

                    <div v-else class="text-center py-12 text-gray-500">
                        <p>Pilih filter dan klik "Tampilkan Laporan" untuk melihat arus kas.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
