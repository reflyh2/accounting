<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AccountingReportTabs from '@/Tabs/AccountingReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import {
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    BuildingLibraryIcon,
    CurrencyDollarIcon,
    ScaleIcon,
    ArchiveBoxIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
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
    router.get(route('operational-reconciliation.index'), form.value, {
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

function formatCompact(number) {
    const abs = Math.abs(number ?? 0);
    const sign = number < 0 ? '-' : '';
    if (abs >= 1e9) return sign + (abs / 1e9).toFixed(1) + ' M';
    if (abs >= 1e6) return sign + (abs / 1e6).toFixed(1) + ' Jt';
    if (abs >= 1e3) return sign + (abs / 1e3).toFixed(0) + ' Rb';
    return sign + abs.toFixed(0);
}

function profitClass(amount) {
    if (amount > 0) return 'text-green-700';
    if (amount < 0) return 'text-red-700';
    return 'text-gray-500';
}

// Charts
const profitTrendChartData = computed(() => ({
    labels: props.chartData?.monthlyProfitTrend?.labels || [],
    datasets: [
        {
            label: props.chartData?.monthlyProfitTrend?.datasets?.[0]?.label || 'Pendapatan',
            data: props.chartData?.monthlyProfitTrend?.datasets?.[0]?.data || [],
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
        },
        {
            label: props.chartData?.monthlyProfitTrend?.datasets?.[1]?.label || 'Laba Bersih',
            data: props.chartData?.monthlyProfitTrend?.datasets?.[1]?.data || [],
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
        },
    ],
}));

const profitTrendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top', labels: { usePointStyle: true, padding: 15 } },
        tooltip: {
            callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` },
        },
    },
    scales: {
        y: { ticks: { callback: (v) => formatCompact(v) } },
    },
};

const expenseChartData = computed(() => ({
    labels: props.chartData?.expenseBreakdown?.labels || [],
    datasets: [{
        data: props.chartData?.expenseBreakdown?.data || [],
        backgroundColor: [
            'rgba(239, 68, 68, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(107, 114, 128, 0.8)',
            'rgba(139, 92, 246, 0.8)',
        ],
        borderWidth: 0,
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%',
    plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { size: 11 } } },
        tooltip: {
            callbacks: { label: (ctx) => `${ctx.label}: ${formatCurrency(ctx.raw)}` },
        },
    },
};

const assetChartData = computed(() => ({
    labels: props.chartData?.assetComposition?.labels || [],
    datasets: [{
        data: props.chartData?.assetComposition?.data || [],
        backgroundColor: [
            'rgba(16, 185, 129, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(99, 102, 241, 0.8)',
            'rgba(107, 114, 128, 0.8)',
        ],
        borderWidth: 0,
    }],
}));

const revenueVsCogsChartData = computed(() => ({
    labels: props.chartData?.revenueVsCogs?.labels || [],
    datasets: [
        {
            label: props.chartData?.revenueVsCogs?.datasets?.[0]?.label || 'Pendapatan',
            data: props.chartData?.revenueVsCogs?.datasets?.[0]?.data || [],
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1,
            borderRadius: 4,
        },
        {
            label: props.chartData?.revenueVsCogs?.datasets?.[1]?.label || 'HPP',
            data: props.chartData?.revenueVsCogs?.datasets?.[1]?.data || [],
            backgroundColor: 'rgba(239, 68, 68, 0.8)',
            borderColor: 'rgb(239, 68, 68)',
            borderWidth: 1,
            borderRadius: 4,
        },
    ],
}));

const revenueVsCogsOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top', labels: { usePointStyle: true, padding: 15 } },
        tooltip: {
            callbacks: { label: (ctx) => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` },
        },
    },
    scales: {
        y: { ticks: { callback: (v) => formatCompact(v) } },
    },
};
</script>

<template>
    <Head title="Ringkasan Akuntansi" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ringkasan Akuntansi</h2>
        </template>

        <div class="mx-auto">
            <AccountingReportTabs activeTab="operational-reconciliation.index" />

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

                    <template v-if="summaryData">
                        <!-- Profitability Cards -->
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Laba Rugi Periode</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                            <!-- Revenue -->
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-blue-800">Pendapatan</h4>
                                    <CurrencyDollarIcon class="w-5 h-5 text-blue-600" />
                                </div>
                                <p class="text-xl font-bold text-blue-900">{{ formatCurrency(summaryData.revenue) }}</p>
                                <p v-if="summaryData.other_revenue" class="text-xs text-blue-600 mt-1">Pendapatan Lainnya: {{ formatCurrency(summaryData.other_revenue) }}</p>
                            </div>

                            <!-- HPP (COGS) -->
                            <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border border-red-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-red-800">Harga Pokok Penjualan</h4>
                                    <ArrowTrendingDownIcon class="w-5 h-5 text-red-600" />
                                </div>
                                <p class="text-xl font-bold text-red-900">{{ formatCurrency(summaryData.cogs) }}</p>
                            </div>

                            <!-- Gross Profit -->
                            <div :class="[
                                'rounded-xl p-5 border',
                                summaryData.gross_profit >= 0
                                    ? 'bg-gradient-to-br from-emerald-50 to-emerald-100 border-emerald-200'
                                    : 'bg-gradient-to-br from-red-50 to-red-100 border-red-200'
                            ]">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 :class="['text-sm font-semibold', summaryData.gross_profit >= 0 ? 'text-emerald-800' : 'text-red-800']">Laba Kotor</h4>
                                    <component :is="summaryData.gross_profit >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                                        :class="['w-5 h-5', summaryData.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600']"
                                    />
                                </div>
                                <p :class="['text-xl font-bold', summaryData.gross_profit >= 0 ? 'text-emerald-900' : 'text-red-900']">
                                    {{ formatCurrency(summaryData.gross_profit) }}
                                </p>
                                <p :class="['text-xs mt-1', summaryData.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600']">
                                    Margin {{ summaryData.gross_margin }}%
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                            <!-- Other Costs (non-HPP) -->
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-5 border border-orange-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-orange-800">Beban Usaha</h4>
                                    <ArrowTrendingDownIcon class="w-5 h-5 text-orange-600" />
                                </div>
                                <p class="text-xl font-bold text-orange-900">{{ formatCurrency(summaryData.total_expenses) }}</p>
                                <div class="mt-2 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-orange-600">Operasional</span>
                                        <span class="text-orange-800">{{ formatCurrency(summaryData.operational_expenses) }}</span>
                                    </div>
                                    <div v-if="summaryData.depreciation" class="flex justify-between text-xs">
                                        <span class="text-orange-600">Penyusutan</span>
                                        <span class="text-orange-800">{{ formatCurrency(summaryData.depreciation) }}</span>
                                    </div>
                                    <div v-if="summaryData.other_expenses" class="flex justify-between text-xs">
                                        <span class="text-orange-600">Lainnya</span>
                                        <span class="text-orange-800">{{ formatCurrency(summaryData.other_expenses) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Net Profit -->
                            <div :class="[
                                'rounded-xl p-5 border',
                                summaryData.net_profit >= 0
                                    ? 'bg-gradient-to-br from-green-50 to-green-100 border-green-200'
                                    : 'bg-gradient-to-br from-red-50 to-red-100 border-red-200'
                            ]">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 :class="['text-sm font-semibold', summaryData.net_profit >= 0 ? 'text-green-800' : 'text-red-800']">Laba Bersih</h4>
                                    <component :is="summaryData.net_profit >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                                        :class="['w-5 h-5', summaryData.net_profit >= 0 ? 'text-green-600' : 'text-red-600']"
                                    />
                                </div>
                                <p :class="['text-xl font-bold', summaryData.net_profit >= 0 ? 'text-green-900' : 'text-red-900']">
                                    {{ formatCurrency(summaryData.net_profit) }}
                                </p>
                                <p :class="['text-xs mt-1', summaryData.net_profit >= 0 ? 'text-green-600' : 'text-red-600']">
                                    Margin {{ summaryData.net_margin }}%
                                </p>
                            </div>
                        </div>

                        <!-- Balance Sheet Cards -->
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Posisi Keuangan</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                            <!-- Total Assets -->
                            <div class="bg-white rounded-xl p-5 border-2 border-indigo-200 shadow-sm">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-indigo-800">Total Aset</h4>
                                    <BuildingLibraryIcon class="w-5 h-5 text-indigo-600" />
                                </div>
                                <p class="text-xl font-bold text-indigo-900">{{ formatCurrency(summaryData.total_assets) }}</p>
                            </div>

                            <!-- Total Liabilities -->
                            <div class="bg-white rounded-xl p-5 border-2 border-red-200 shadow-sm">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-red-800">Total Liabilitas</h4>
                                    <DocumentTextIcon class="w-5 h-5 text-red-600" />
                                </div>
                                <p class="text-xl font-bold text-red-900">{{ formatCurrency(summaryData.total_liabilities) }}</p>
                            </div>

                            <!-- Total Equity -->
                            <div class="bg-white rounded-xl p-5 border-2 border-green-200 shadow-sm">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-green-800">Total Ekuitas</h4>
                                    <ScaleIcon class="w-5 h-5 text-green-600" />
                                </div>
                                <p class="text-xl font-bold text-green-900">{{ formatCurrency(summaryData.total_equity) }}</p>
                            </div>

                            <!-- Cash & Bank -->
                            <div class="bg-white rounded-xl p-5 border-2 border-teal-200 shadow-sm">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-teal-800">Kas & Bank</h4>
                                    <BanknotesIcon class="w-5 h-5 text-teal-600" />
                                </div>
                                <p class="text-xl font-bold text-teal-900">{{ formatCurrency(summaryData.cash_and_bank) }}</p>
                            </div>
                        </div>

                        <!-- Quick Ratios -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Piutang Usaha</span>
                                    <span class="font-bold text-gray-900">{{ formatCurrency(summaryData.receivables) }}</span>
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Hutang Usaha</span>
                                    <span class="font-bold text-gray-900">{{ formatCurrency(summaryData.payables) }}</span>
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Persediaan</span>
                                    <span class="font-bold text-gray-900">{{ formatCurrency(summaryData.inventory) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div v-if="chartData" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Monthly Profit Trend -->
                            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm lg:col-span-2">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Pendapatan & Laba Bersih (6 Bulan)</h3>
                                <div class="h-72">
                                    <Line
                                        v-if="profitTrendChartData.labels.length > 0"
                                        :data="profitTrendChartData"
                                        :options="profitTrendOptions"
                                    />
                                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                                        Tidak ada data
                                    </div>
                                </div>
                            </div>

                            <!-- Revenue vs COGS -->
                            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Pendapatan vs HPP (6 Bulan)</h3>
                                <div class="h-64">
                                    <Bar
                                        v-if="revenueVsCogsChartData.labels.length > 0"
                                        :data="revenueVsCogsChartData"
                                        :options="revenueVsCogsOptions"
                                    />
                                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                                        Tidak ada data
                                    </div>
                                </div>
                            </div>

                            <!-- Expense Breakdown -->
                            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Komposisi Beban</h3>
                                <div class="h-64">
                                    <Doughnut
                                        v-if="expenseChartData.labels.length > 0"
                                        :data="expenseChartData"
                                        :options="doughnutOptions"
                                    />
                                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                                        Tidak ada data
                                    </div>
                                </div>
                            </div>

                            <!-- Asset Composition -->
                            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Komposisi Aset</h3>
                                <div class="h-64">
                                    <Doughnut
                                        v-if="assetChartData.labels.length > 0"
                                        :data="assetChartData"
                                        :options="doughnutOptions"
                                    />
                                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                                        Tidak ada data
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-if="!summaryData" class="text-center py-12 text-gray-500">
                        <BanknotesIcon class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                        <p>Pilih filter dan klik "Tampilkan Laporan" untuk melihat ringkasan akuntansi.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
