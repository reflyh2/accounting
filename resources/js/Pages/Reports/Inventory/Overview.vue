<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import InventoryReportTabs from '@/Tabs/InventoryReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { CubeIcon, CurrencyDollarIcon, ArrowsRightLeftIcon, ArchiveBoxIcon } from '@heroicons/vue/24/outline';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    companies: Array,
    branches: Array,
    filters: Object,
    summaryData: Object,
    chartData: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('inventory-reports.index'), form.value, {
        preserveState: false,
        preserveScroll: true,
    });
}

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number ?? 0);
}

function formatCurrency(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(number ?? 0);
}

// Charts
const stockByCategoryChartData = computed(() => ({
    labels: props.chartData?.stockByCategory?.labels || [],
    datasets: [{
        label: 'Jumlah Stok',
        data: props.chartData?.stockByCategory?.data || [],
        backgroundColor: 'rgba(59, 130, 246, 0.8)',
        borderColor: 'rgb(59, 130, 246)',
        borderWidth: 1,
        borderRadius: 4,
    }],
}));

const stockByCategoryOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (context) => `${formatNumber(context.raw)} unit`,
            },
        },
    },
};

const topItemsChartData = computed(() => ({
    labels: props.chartData?.topItemsByValue?.labels || [],
    datasets: [{
        label: 'Nilai Persediaan',
        data: props.chartData?.topItemsByValue?.data || [],
        backgroundColor: [
            'rgba(16, 185, 129, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(20, 184, 166, 0.8)',
            'rgba(99, 102, 241, 0.8)',
            'rgba(236, 72, 153, 0.8)',
            'rgba(234, 179, 8, 0.8)',
            'rgba(107, 114, 128, 0.8)',
        ],
        borderWidth: 1,
        borderRadius: 4,
    }],
}));

const topItemsChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (context) => formatCurrency(context.raw),
            },
        },
    },
    scales: {
        x: {
            ticks: {
                callback: (value) => formatCurrency(value),
            },
        },
    },
};

const movementTrendChartData = computed(() => ({
    labels: props.chartData?.movementTrend?.labels || [],
    datasets: [
        {
            label: props.chartData?.movementTrend?.datasets?.[0]?.label || 'Masuk',
            data: props.chartData?.movementTrend?.datasets?.[0]?.data || [],
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
        },
        {
            label: props.chartData?.movementTrend?.datasets?.[1]?.label || 'Keluar',
            data: props.chartData?.movementTrend?.datasets?.[1]?.data || [],
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.4,
        },
    ],
}));

const movementTrendChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            labels: { usePointStyle: true, padding: 15 },
        },
        tooltip: {
            callbacks: {
                label: (context) => `${context.dataset.label}: ${formatNumber(context.raw)}`,
            },
        },
    },
};

const movementByTypeChartData = computed(() => ({
    labels: props.chartData?.movementByType?.labels || [],
    datasets: [{
        data: props.chartData?.movementByType?.data || [],
        backgroundColor: [
            'rgba(16, 185, 129, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(59, 130, 246, 0.8)',
        ],
        borderWidth: 0,
    }],
}));

const movementByTypeChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: { usePointStyle: true, padding: 15, font: { size: 11 } },
        },
    },
};
</script>

<template>
    <Head title="Laporan Persediaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Laporan Persediaan</h2>
        </template>

        <div class="mx-auto">
            <InventoryReportTabs activeTab="inventory-reports.index" />

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

                    <!-- Summary Cards -->
                    <div v-if="summaryData" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Total Items Card -->
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-blue-800">Item Aktif</h3>
                                <div class="p-2 bg-blue-200 rounded-lg">
                                    <CubeIcon class="w-6 h-6 text-blue-700" />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-600">Produk Unik</span>
                                    <span class="text-2xl font-bold text-blue-900">{{ formatNumber(summaryData.total_items) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Value Card -->
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 border border-green-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-green-800">Nilai Persediaan</h3>
                                <div class="p-2 bg-green-200 rounded-lg">
                                    <CurrencyDollarIcon class="w-6 h-6 text-green-700" />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-green-600">Total Nilai</span>
                                    <span class="text-xl font-bold text-green-900">{{ formatCurrency(summaryData.total_value) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Transactions Card -->
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 border border-purple-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-purple-800">Transaksi</h3>
                                <div class="p-2 bg-purple-200 rounded-lg">
                                    <ArrowsRightLeftIcon class="w-6 h-6 text-purple-700" />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-purple-600">Total</span>
                                    <span class="text-2xl font-bold text-purple-900">{{ formatNumber(summaryData.total_transactions) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Transactions by Type Card -->
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-6 border border-orange-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-orange-800">Per Tipe</h3>
                                <div class="p-2 bg-orange-200 rounded-lg">
                                    <ArchiveBoxIcon class="w-6 h-6 text-orange-700" />
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div v-for="(count, type) in summaryData.transactions_by_type" :key="type" class="flex justify-between text-xs">
                                    <span class="text-orange-500 capitalize">{{ type }}</span>
                                    <span class="text-orange-700 font-medium">{{ formatNumber(count) }}</span>
                                </div>
                                <div v-if="!Object.keys(summaryData.transactions_by_type || {}).length" class="text-xs text-orange-400">
                                    Tidak ada transaksi
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div v-if="chartData" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Movement Trend Chart -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Mutasi Bulanan</h3>
                            <div class="h-72">
                                <Line
                                    v-if="movementTrendChartData.labels.length > 0"
                                    :data="movementTrendChartData"
                                    :options="movementTrendChartOptions"
                                />
                                <div v-else class="h-full flex items-center justify-center text-gray-400">
                                    Tidak ada data untuk periode ini
                                </div>
                            </div>
                        </div>

                        <!-- Top Items by Value Chart -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Top 10 Produk (Nilai)</h3>
                            <div class="h-64">
                                <Bar
                                    v-if="topItemsChartData.labels.length > 0"
                                    :data="topItemsChartData"
                                    :options="topItemsChartOptions"
                                />
                                <div v-else class="h-full flex items-center justify-center text-gray-400">
                                    Tidak ada data
                                </div>
                            </div>
                        </div>

                        <!-- Stock by Category Chart -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Stok per Kategori</h3>
                            <div class="h-64">
                                <Bar
                                    v-if="stockByCategoryChartData.labels.length > 0"
                                    :data="stockByCategoryChartData"
                                    :options="stockByCategoryOptions"
                                />
                                <div v-else class="h-full flex items-center justify-center text-gray-400">
                                    Tidak ada data
                                </div>
                            </div>
                        </div>

                        <!-- Movement by Type Chart -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Transaksi per Tipe</h3>
                            <div class="h-64">
                                <Doughnut
                                    v-if="movementByTypeChartData.labels.length > 0"
                                    :data="movementByTypeChartData"
                                    :options="movementByTypeChartOptions"
                                />
                                <div v-else class="h-full flex items-center justify-center text-gray-400">
                                    Tidak ada data untuk periode ini
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!summaryData" class="text-center py-12 text-gray-500">
                        <CubeIcon class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                        <p>Pilih filter dan klik "Tampilkan Laporan" untuk melihat ringkasan persediaan.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
