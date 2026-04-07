<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import PayableReceivableReportTabs from '@/Tabs/PayableReceivableReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import {
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    UserGroupIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ScaleIcon,
    ClockIcon,
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
    Filler,
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
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('payable-receivable-overview.index'), form.value, {
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

// Charts
const trendChartData = computed(() => ({
    labels: props.chartData?.monthlyTrend?.labels || [],
    datasets: [
        {
            label: props.chartData?.monthlyTrend?.datasets?.[0]?.label || 'Piutang',
            data: props.chartData?.monthlyTrend?.datasets?.[0]?.data || [],
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
        },
        {
            label: props.chartData?.monthlyTrend?.datasets?.[1]?.label || 'Hutang',
            data: props.chartData?.monthlyTrend?.datasets?.[1]?.data || [],
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            fill: true,
            tension: 0.4,
        },
    ],
}));

const trendOptions = {
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

const agingColors = [
    'rgba(16, 185, 129, 0.8)',
    'rgba(59, 130, 246, 0.8)',
    'rgba(245, 158, 11, 0.8)',
    'rgba(239, 68, 68, 0.8)',
    'rgba(139, 92, 246, 0.8)',
];

const payableAgingChartData = computed(() => ({
    labels: props.chartData?.payableAgingChart?.labels || [],
    datasets: [{
        data: props.chartData?.payableAgingChart?.data || [],
        backgroundColor: agingColors,
        borderWidth: 0,
    }],
}));

const receivableAgingChartData = computed(() => ({
    labels: props.chartData?.receivableAgingChart?.labels || [],
    datasets: [{
        data: props.chartData?.receivableAgingChart?.data || [],
        backgroundColor: agingColors,
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
</script>

<template>
    <Head title="Ringkasan Hutang / Piutang" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ringkasan Hutang / Piutang</h2>
        </template>

        <div class="mx-auto">
            <PayableReceivableReportTabs activeTab="payable-receivable-overview.index" />

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
                        <AppInput v-model="form.end_date" type="date" label="Per Tanggal" />
                    </div>

                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <template v-if="summaryData">
                        <!-- Main KPI Cards -->
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Ringkasan</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                            <!-- Total Receivable -->
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-blue-800">Total Piutang</h4>
                                    <ArrowTrendingUpIcon class="w-5 h-5 text-blue-600" />
                                </div>
                                <p class="text-xl font-bold text-blue-900">{{ formatCurrency(summaryData.total_receivable) }}</p>
                                <div class="mt-2 flex justify-between text-xs">
                                    <span class="text-blue-600">{{ summaryData.receivable_partner_count }} mitra</span>
                                </div>
                            </div>

                            <!-- Total Payable -->
                            <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border border-red-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-red-800">Total Hutang</h4>
                                    <ArrowTrendingDownIcon class="w-5 h-5 text-red-600" />
                                </div>
                                <p class="text-xl font-bold text-red-900">{{ formatCurrency(summaryData.total_payable) }}</p>
                                <div class="mt-2 flex justify-between text-xs">
                                    <span class="text-red-600">{{ summaryData.payable_partner_count }} mitra</span>
                                </div>
                            </div>

                            <!-- Net Position -->
                            <div :class="[
                                'rounded-xl p-5 border',
                                summaryData.net_position >= 0
                                    ? 'bg-gradient-to-br from-green-50 to-green-100 border-green-200'
                                    : 'bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200'
                            ]">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 :class="['text-sm font-semibold', summaryData.net_position >= 0 ? 'text-green-800' : 'text-orange-800']">Posisi Bersih</h4>
                                    <ScaleIcon :class="['w-5 h-5', summaryData.net_position >= 0 ? 'text-green-600' : 'text-orange-600']" />
                                </div>
                                <p :class="['text-xl font-bold', summaryData.net_position >= 0 ? 'text-green-900' : 'text-orange-900']">
                                    {{ formatCurrency(summaryData.net_position) }}
                                </p>
                                <p :class="['text-xs mt-1', summaryData.net_position >= 0 ? 'text-green-600' : 'text-orange-600']">
                                    {{ summaryData.net_position >= 0 ? 'Piutang > Hutang' : 'Hutang > Piutang' }}
                                </p>
                            </div>

                            <!-- Overdue Summary -->
                            <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-5 border border-amber-200">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-amber-800">Jatuh Tempo</h4>
                                    <ExclamationTriangleIcon class="w-5 h-5 text-amber-600" />
                                </div>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-amber-700">Piutang</span>
                                        <span class="font-semibold text-amber-900">{{ formatCurrency(summaryData.receivable_overdue) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-amber-700">Hutang</span>
                                        <span class="font-semibold text-amber-900">{{ formatCurrency(summaryData.payable_overdue) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Aging Breakdown -->
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Rincian Umur</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                            <!-- Receivable Aging -->
                            <div class="bg-white rounded-xl p-5 border-2 border-blue-200 shadow-sm">
                                <h4 class="text-sm font-semibold text-blue-800 mb-3">Umur Piutang</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Belum Jatuh Tempo</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.receivable_aging.not_yet_due) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">1 - 30 Hari</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.receivable_aging.days_1_30) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">31 - 60 Hari</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.receivable_aging.days_31_60) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">61 - 90 Hari</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.receivable_aging.days_61_90) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">&gt; 90 Hari</span>
                                        <span class="font-medium text-red-600">{{ formatCurrency(summaryData.receivable_aging.days_91_plus) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Payable Aging -->
                            <div class="bg-white rounded-xl p-5 border-2 border-red-200 shadow-sm">
                                <h4 class="text-sm font-semibold text-red-800 mb-3">Umur Hutang</h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Belum Jatuh Tempo</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.payable_aging.not_yet_due) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">1 - 30 Hari</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.payable_aging.days_1_30) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">31 - 60 Hari</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.payable_aging.days_31_60) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">61 - 90 Hari</span>
                                        <span class="font-medium text-gray-900">{{ formatCurrency(summaryData.payable_aging.days_61_90) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">&gt; 90 Hari</span>
                                        <span class="font-medium text-red-600">{{ formatCurrency(summaryData.payable_aging.days_91_plus) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Partners -->
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Mitra Terbesar</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                            <!-- Top Receivable Partners -->
                            <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                                <h4 class="text-sm font-semibold text-blue-800 mb-3">Top 5 Piutang</h4>
                                <div v-if="summaryData.receivable_top_partners.length > 0" class="space-y-2">
                                    <div v-for="(partner, idx) in summaryData.receivable_top_partners" :key="idx" class="flex justify-between text-sm">
                                        <span class="text-gray-600 truncate mr-2">{{ partner.name }}</span>
                                        <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(partner.amount) }}</span>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-400">Tidak ada data</p>
                            </div>

                            <!-- Top Payable Partners -->
                            <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                                <h4 class="text-sm font-semibold text-red-800 mb-3">Top 5 Hutang</h4>
                                <div v-if="summaryData.payable_top_partners.length > 0" class="space-y-2">
                                    <div v-for="(partner, idx) in summaryData.payable_top_partners" :key="idx" class="flex justify-between text-sm">
                                        <span class="text-gray-600 truncate mr-2">{{ partner.name }}</span>
                                        <span class="font-medium text-gray-900 whitespace-nowrap">{{ formatCurrency(partner.amount) }}</span>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-400">Tidak ada data</p>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div v-if="chartData" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Monthly Trend -->
                            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm lg:col-span-2">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Hutang & Piutang (6 Bulan)</h3>
                                <div class="h-72">
                                    <Line
                                        v-if="trendChartData.labels.length > 0"
                                        :data="trendChartData"
                                        :options="trendOptions"
                                    />
                                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                                        Tidak ada data
                                    </div>
                                </div>
                            </div>

                            <!-- Receivable Aging Chart -->
                            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Komposisi Umur Piutang</h3>
                                <div class="h-64">
                                    <Doughnut
                                        v-if="receivableAgingChartData.datasets[0].data.some(v => v > 0)"
                                        :data="receivableAgingChartData"
                                        :options="doughnutOptions"
                                    />
                                    <div v-else class="h-full flex items-center justify-center text-gray-400">
                                        Tidak ada data
                                    </div>
                                </div>
                            </div>

                            <!-- Payable Aging Chart -->
                            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                                <h3 class="text-lg font-semibold text-gray-800 mb-4">Komposisi Umur Hutang</h3>
                                <div class="h-64">
                                    <Doughnut
                                        v-if="payableAgingChartData.datasets[0].data.some(v => v > 0)"
                                        :data="payableAgingChartData"
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
                        <p>Pilih filter dan klik "Tampilkan Laporan" untuk melihat ringkasan hutang / piutang.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
