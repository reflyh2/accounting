<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import InventoryReportTabs from '@/Tabs/InventoryReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';
import ReportTable from '@/Components/ReportTable.vue';
import ReportTH from '@/Components/ReportTH.vue';
import ReportTD from '@/Components/ReportTD.vue';

const props = defineProps({
    companies: Array,
    branches: Array,
    locations: Array,
    categories: Array,
    transactionTypes: Array,
    filters: Object,
    data: Object,
    totals: Object,
    typeLabels: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    location_id: props.filters.location_id || '',
    category_id: props.filters.category_id || '',
    transaction_type: props.filters.transaction_type || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('inventory-reports.stock-movement'), form.value, {
        preserveState: false,
        preserveScroll: true,
    });
}

function formatNumber(number, decimals = 3) {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: decimals }).format(number ?? 0);
}

function formatCurrency(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(number ?? 0);
}

function formatUnitCost(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 4,
    }).format(number ?? 0);
}
</script>

<template>
    <Head title="Laporan Mutasi Stok" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Laporan Mutasi Stok</h2>
        </template>

        <div class="mx-auto">
            <InventoryReportTabs activeTab="inventory-reports.stock-movement" />

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
                            v-model="form.location_id"
                            :options="[{ value: '', label: 'Semua Lokasi' }, ...locations.map(l => ({ value: l.id, label: l.name }))]"
                            label="Lokasi"
                            placeholder="Semua Lokasi"
                        />
                        <AppSelect
                            v-model="form.category_id"
                            :options="[{ value: '', label: 'Semua Kategori' }, ...categories.map(c => ({ value: c.id, label: c.name }))]"
                            label="Kategori"
                            placeholder="Semua Kategori"
                        />
                        <AppSelect
                            v-model="form.transaction_type"
                            :options="transactionTypes"
                            label="Tipe Transaksi"
                            placeholder="Semua Tipe"
                        />
                        <AppInput v-model="form.start_date" type="date" label="Dari Tanggal" />
                        <AppInput v-model="form.end_date" type="date" label="Sampai Tanggal" />
                    </div>

                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <!-- Movement Table -->
                    <ReportTable>
                        <thead>
                            <tr class="bg-gray-100">
                                <ReportTH sticky rowspan="2">Produk</ReportTH>
                                <ReportTH sticky rowspan="2">Kategori</ReportTH>
                                <ReportTH sticky rowspan="2">UOM</ReportTH>
                                <ReportTH sticky colspan="3" class="text-center">Saldo Awal</ReportTH>
                                <ReportTH sticky colspan="3" class="text-center bg-green-50">Masuk</ReportTH>
                                <ReportTH sticky colspan="3" class="text-center bg-red-50">Keluar</ReportTH>
                                <ReportTH sticky colspan="3" class="text-center bg-blue-50">Saldo Akhir</ReportTH>
                            </tr>
                            <tr class="bg-gray-100">
                                <ReportTH sticky class="text-right">Qty</ReportTH>
                                <ReportTH sticky class="text-right">Rata-rata</ReportTH>
                                <ReportTH sticky class="text-right">Nilai</ReportTH>
                                <ReportTH sticky class="text-right bg-green-50">Qty</ReportTH>
                                <ReportTH sticky class="text-right bg-green-50">Rata-rata</ReportTH>
                                <ReportTH sticky class="text-right bg-green-50">Nilai</ReportTH>
                                <ReportTH sticky class="text-right bg-red-50">Qty</ReportTH>
                                <ReportTH sticky class="text-right bg-red-50">Rata-rata</ReportTH>
                                <ReportTH sticky class="text-right bg-red-50">Nilai</ReportTH>
                                <ReportTH sticky class="text-right bg-blue-50">Qty</ReportTH>
                                <ReportTH sticky class="text-right bg-blue-50">Rata-rata</ReportTH>
                                <ReportTH sticky class="text-right bg-blue-50">Nilai</ReportTH>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in data.data" :key="item.product_variant_id" class="group hover:bg-gray-50">
                                <ReportTD>
                                    {{ item.product_name }}
                                    <span v-if="item.variant_name !== '-'" class="text-gray-400"> - {{ item.variant_name }}</span>
                                </ReportTD>
                                <ReportTD>{{ item.category }}</ReportTD>
                                <ReportTD>{{ item.uom }}</ReportTD>
                                <!-- Beginning -->
                                <ReportTD class="text-right">{{ formatNumber(item.begin_qty) }}</ReportTD>
                                <ReportTD class="text-right">{{ formatUnitCost(item.begin_avg_cost) }}</ReportTD>
                                <ReportTD class="text-right font-medium">{{ formatCurrency(item.begin_value) }}</ReportTD>
                                <!-- In -->
                                <ReportTD class="text-right bg-green-50/30 text-green-700">{{ formatNumber(item.qty_in) }}</ReportTD>
                                <ReportTD class="text-right bg-green-50/30 text-green-700">{{ formatUnitCost(item.avg_cost_in) }}</ReportTD>
                                <ReportTD class="text-right bg-green-50/30 text-green-700 font-medium">{{ formatCurrency(item.value_in) }}</ReportTD>
                                <!-- Out -->
                                <ReportTD class="text-right bg-red-50/30 text-red-700">{{ formatNumber(item.qty_out) }}</ReportTD>
                                <ReportTD class="text-right bg-red-50/30 text-red-700">{{ formatUnitCost(item.avg_cost_out) }}</ReportTD>
                                <ReportTD class="text-right bg-red-50/30 text-red-700 font-medium">{{ formatCurrency(item.value_out) }}</ReportTD>
                                <!-- Ending -->
                                <ReportTD class="text-right bg-blue-50/30 font-medium">{{ formatNumber(item.end_qty) }}</ReportTD>
                                <ReportTD class="text-right bg-blue-50/30">{{ formatUnitCost(item.end_avg_cost) }}</ReportTD>
                                <ReportTD class="text-right bg-blue-50/30 font-bold">{{ formatCurrency(item.end_value) }}</ReportTD>
                            </tr>
                            <tr v-if="!data.data?.length" class="group">
                                <ReportTD :colspan="15" class="text-center text-gray-500 py-8">
                                    Tidak ada data untuk filter yang dipilih.
                                </ReportTD>
                            </tr>
                        </tbody>
                        <tfoot v-if="data.data?.length && totals">
                            <tr class="group bg-gray-100 font-bold">
                                <ReportTD :colspan="5" class="text-right">Grand Total:</ReportTD>
                                <ReportTD class="text-right">{{ formatCurrency(totals.begin_value) }}</ReportTD>
                                <ReportTD :colspan="2"></ReportTD>
                                <ReportTD class="text-right text-green-700">{{ formatCurrency(totals.value_in) }}</ReportTD>
                                <ReportTD :colspan="2"></ReportTD>
                                <ReportTD class="text-right text-red-700">{{ formatCurrency(totals.value_out) }}</ReportTD>
                                <ReportTD :colspan="2"></ReportTD>
                                <ReportTD class="text-right">{{ formatCurrency(totals.end_value) }}</ReportTD>
                            </tr>
                        </tfoot>
                    </ReportTable>

                    <Pagination v-if="data.data?.length" :links="data.links" class="mt-4" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
