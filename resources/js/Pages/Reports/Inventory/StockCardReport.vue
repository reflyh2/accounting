<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import InventoryReportTabs from '@/Tabs/InventoryReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import ReportTable from '@/Components/ReportTable.vue';
import ReportTH from '@/Components/ReportTH.vue';
import ReportTD from '@/Components/ReportTD.vue';

const props = defineProps({
    companies: Array,
    branches: Array,
    locations: Array,
    products: Array,
    filters: Object,
    data: Array,
    product: Object,
    openingBalance: Number,
    openingValue: Number,
    typeLabels: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    location_id: props.filters.location_id || '',
    product_variant_id: props.filters.product_variant_id || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('inventory-reports.stock-card'), form.value, {
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

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
}

function getTypeLabel(type) {
    return props.typeLabels?.[type] || type;
}

function getTypeBadgeClass(type) {
    const classes = {
        receipt: 'bg-green-100 text-green-800',
        issue: 'bg-red-100 text-red-800',
        adjustment: 'bg-yellow-100 text-yellow-800',
        transfer: 'bg-blue-100 text-blue-800',
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
}

const closingBalance = props.data?.length
    ? props.data[props.data.length - 1].balance
    : props.openingBalance;

const closingValue = props.data?.length
    ? props.data[props.data.length - 1].balance_value
    : props.openingValue;
</script>

<template>
    <Head title="Kartu Stok" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Kartu Stok</h2>
        </template>

        <div class="mx-auto">
            <InventoryReportTabs activeTab="inventory-reports.stock-card" />

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
                            v-model="form.product_variant_id"
                            :options="[{ value: '', label: '-- Pilih Produk --' }, ...products]"
                            label="Produk"
                            placeholder="Pilih Produk"
                        />
                        <AppInput v-model="form.start_date" type="date" label="Dari Tanggal" />
                        <AppInput v-model="form.end_date" type="date" label="Sampai Tanggal" />
                    </div>

                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <!-- Product Info Header -->
                    <div v-if="product" class="mb-4 p-4 bg-blue-50 rounded-lg">
                        <div class="flex gap-8 flex-wrap">
                            <div>
                                <span class="text-sm text-blue-600">Produk:</span>
                                <span class="ml-2 font-bold text-blue-900">
                                    {{ product.product?.name }}{{ product.name ? ` - ${product.name}` : '' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-sm text-blue-600">UOM:</span>
                                <span class="ml-2 font-bold text-blue-900">{{ product.uom?.abbreviation || product.uom?.name || '-' }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-blue-600">Saldo Awal:</span>
                                <span class="ml-2 font-bold text-blue-900">{{ formatNumber(openingBalance) }}</span>
                                <span class="ml-1 text-sm text-blue-600">({{ formatCurrency(openingValue) }})</span>
                            </div>
                            <div>
                                <span class="text-sm text-blue-600">Saldo Akhir:</span>
                                <span class="ml-2 font-bold text-blue-900">{{ formatNumber(closingBalance) }}</span>
                                <span class="ml-1 text-sm text-blue-600">({{ formatCurrency(closingValue) }})</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Card Table -->
                    <template v-if="product && data !== null">
                        <ReportTable>
                            <thead>
                                <tr class="bg-gray-100">
                                    <ReportTH sticky>Tanggal</ReportTH>
                                    <ReportTH sticky>No. Transaksi</ReportTH>
                                    <ReportTH sticky class="text-center">Tipe</ReportTH>
                                    <ReportTH sticky>Dari</ReportTH>
                                    <ReportTH sticky>Ke</ReportTH>
                                    <ReportTH sticky class="text-right">Masuk</ReportTH>
                                    <ReportTH sticky class="text-right">Keluar</ReportTH>
                                    <ReportTH sticky class="text-right">Biaya/Unit</ReportTH>
                                    <ReportTH sticky class="text-right">Saldo</ReportTH>
                                    <ReportTH sticky class="text-right">Nilai Saldo</ReportTH>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Opening Balance Row -->
                                <tr class="group bg-gray-50 font-semibold">
                                    <ReportTD :colspan="5">Saldo Awal</ReportTD>
                                    <ReportTD class="text-right">-</ReportTD>
                                    <ReportTD class="text-right">-</ReportTD>
                                    <ReportTD class="text-right">-</ReportTD>
                                    <ReportTD class="text-right font-bold">{{ formatNumber(openingBalance) }}</ReportTD>
                                    <ReportTD class="text-right font-bold">{{ formatCurrency(openingValue) }}</ReportTD>
                                </tr>

                                <!-- Movement Rows -->
                                <tr v-for="(item, index) in data" :key="index" class="group hover:bg-gray-50">
                                    <ReportTD>{{ formatDate(item.date) }}</ReportTD>
                                    <ReportTD class="font-medium">{{ item.transaction_number }}</ReportTD>
                                    <ReportTD class="text-center">
                                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', getTypeBadgeClass(item.transaction_type)]">
                                            {{ getTypeLabel(item.transaction_type) }}
                                        </span>
                                    </ReportTD>
                                    <ReportTD>{{ item.location_from || '-' }}</ReportTD>
                                    <ReportTD>{{ item.location_to || '-' }}</ReportTD>
                                    <ReportTD class="text-right text-green-700 font-medium">{{ item.qty_in > 0 ? formatNumber(item.qty_in) : '-' }}</ReportTD>
                                    <ReportTD class="text-right text-red-700 font-medium">{{ item.qty_out > 0 ? formatNumber(item.qty_out) : '-' }}</ReportTD>
                                    <ReportTD class="text-right">{{ formatUnitCost(item.unit_cost) }}</ReportTD>
                                    <ReportTD class="text-right font-bold">{{ formatNumber(item.balance) }}</ReportTD>
                                    <ReportTD class="text-right font-bold">{{ formatCurrency(item.balance_value) }}</ReportTD>
                                </tr>

                                <!-- Closing Balance Row -->
                                <tr class="group bg-gray-50 font-semibold">
                                    <ReportTD :colspan="5">Saldo Akhir</ReportTD>
                                    <ReportTD class="text-right">-</ReportTD>
                                    <ReportTD class="text-right">-</ReportTD>
                                    <ReportTD class="text-right">-</ReportTD>
                                    <ReportTD class="text-right font-bold">{{ formatNumber(closingBalance) }}</ReportTD>
                                    <ReportTD class="text-right font-bold">{{ formatCurrency(closingValue) }}</ReportTD>
                                </tr>

                                <tr v-if="!data?.length" class="group">
                                    <ReportTD :colspan="10" class="text-center text-gray-500 py-8">
                                        Tidak ada mutasi untuk periode ini.
                                    </ReportTD>
                                </tr>
                            </tbody>
                        </ReportTable>
                    </template>

                    <!-- Empty State -->
                    <div v-else-if="!product" class="text-center py-12 text-gray-500">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                        </svg>
                        <p>Pilih produk dan klik "Tampilkan Laporan" untuk melihat kartu stok.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
