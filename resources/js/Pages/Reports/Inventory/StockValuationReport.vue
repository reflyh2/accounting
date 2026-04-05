<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
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
    filters: Object,
    data: Object,
    totals: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    location_id: props.filters.location_id || '',
    category_id: props.filters.category_id || '',
});

function generateReport() {
    router.get(route('inventory-reports.stock-valuation'), form.value, {
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
    <Head title="Laporan Valuasi Stok" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Laporan Valuasi Stok</h2>
        </template>

        <div class="mx-auto">
            <InventoryReportTabs activeTab="inventory-reports.stock-valuation" />

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
                    </div>

                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <!-- Summary -->
                    <div v-if="totals" class="mb-4 p-4 bg-green-50 rounded-lg flex gap-8 flex-wrap">
                        <div>
                            <span class="text-sm text-green-600">Produk Unik:</span>
                            <span class="ml-2 font-bold text-green-900">{{ formatNumber(totals.distinct_products, 0) }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-green-600">Total On Hand:</span>
                            <span class="ml-2 font-bold text-green-900">{{ formatNumber(totals.total_qty_on_hand) }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-green-600">Total Reserved:</span>
                            <span class="ml-2 font-bold text-green-900">{{ formatNumber(totals.total_qty_reserved) }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-green-600">Total Available:</span>
                            <span class="ml-2 font-bold text-green-900">{{ formatNumber(totals.total_qty_available) }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-green-600">Total Nilai On Hand:</span>
                            <span class="ml-2 font-bold text-green-900">{{ formatCurrency(totals.total_value_on_hand) }}</span>
                        </div>
                    </div>

                    <!-- Valuation Table -->
                    <ReportTable>
                        <thead>
                            <tr class="bg-gray-100">
                                <ReportTH sticky rowspan="2">Produk</ReportTH>
                                <ReportTH sticky rowspan="2">Kategori</ReportTH>
                                <ReportTH sticky rowspan="2">Lokasi</ReportTH>
                                <ReportTH sticky rowspan="2">UOM</ReportTH>
                                <ReportTH sticky rowspan="2" class="text-right">Biaya Rata-rata</ReportTH>
                                <ReportTH sticky colspan="2" class="text-center bg-blue-50">On Hand</ReportTH>
                                <ReportTH sticky colspan="2" class="text-center bg-amber-50">Reserved</ReportTH>
                                <ReportTH sticky colspan="2" class="text-center bg-green-50">Available</ReportTH>
                            </tr>
                            <tr class="bg-gray-100">
                                <ReportTH sticky class="text-right bg-blue-50">Qty</ReportTH>
                                <ReportTH sticky class="text-right bg-blue-50">Nilai</ReportTH>
                                <ReportTH sticky class="text-right bg-amber-50">Qty</ReportTH>
                                <ReportTH sticky class="text-right bg-amber-50">Nilai</ReportTH>
                                <ReportTH sticky class="text-right bg-green-50">Qty</ReportTH>
                                <ReportTH sticky class="text-right bg-green-50">Nilai</ReportTH>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in data.data" :key="item.id" class="group hover:bg-gray-50">
                                <ReportTD>
                                    {{ item.product_name }}
                                    <span v-if="item.variant_name !== '-'" class="text-gray-400"> - {{ item.variant_name }}</span>
                                </ReportTD>
                                <ReportTD>{{ item.category }}</ReportTD>
                                <ReportTD>{{ item.location }}</ReportTD>
                                <ReportTD>{{ item.uom }}</ReportTD>
                                <ReportTD class="text-right">{{ formatUnitCost(item.avg_cost) }}</ReportTD>
                                <ReportTD class="text-right bg-blue-50/30">{{ formatNumber(item.qty_on_hand) }}</ReportTD>
                                <ReportTD class="text-right bg-blue-50/30 font-medium">{{ formatCurrency(item.value_on_hand) }}</ReportTD>
                                <ReportTD class="text-right bg-amber-50/30">{{ formatNumber(item.qty_reserved) }}</ReportTD>
                                <ReportTD class="text-right bg-amber-50/30 font-medium">{{ formatCurrency(item.value_reserved) }}</ReportTD>
                                <ReportTD class="text-right bg-green-50/30">{{ formatNumber(item.qty_available) }}</ReportTD>
                                <ReportTD class="text-right bg-green-50/30 font-medium">{{ formatCurrency(item.value_available) }}</ReportTD>
                            </tr>
                            <tr v-if="!data.data?.length" class="group">
                                <ReportTD :colspan="11" class="text-center text-gray-500 py-8">
                                    Tidak ada data untuk filter yang dipilih.
                                </ReportTD>
                            </tr>
                        </tbody>
                        <tfoot v-if="data.data?.length">
                            <tr class="group bg-gray-100 font-bold">
                                <ReportTD :colspan="5" class="text-right">Grand Total:</ReportTD>
                                <ReportTD class="text-right">{{ formatNumber(totals.total_qty_on_hand) }}</ReportTD>
                                <ReportTD class="text-right">{{ formatCurrency(totals.total_value_on_hand) }}</ReportTD>
                                <ReportTD class="text-right">{{ formatNumber(totals.total_qty_reserved) }}</ReportTD>
                                <ReportTD class="text-right">{{ formatCurrency(totals.total_value_reserved) }}</ReportTD>
                                <ReportTD class="text-right">{{ formatNumber(totals.total_qty_available) }}</ReportTD>
                                <ReportTD class="text-right">{{ formatCurrency(totals.total_value_available) }}</ReportTD>
                            </tr>
                        </tfoot>
                    </ReportTable>

                    <Pagination v-if="data.data?.length" :links="data.links" class="mt-4" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
