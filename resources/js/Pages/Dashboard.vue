<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    CubeIcon,
    BuildingLibraryIcon,
    ScaleIcon,
    ExclamationTriangleIcon,
    CurrencyDollarIcon,
    ArchiveBoxIcon,
} from '@heroicons/vue/24/outline';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    userName: String,
    preferences: Object,
    access: Object,
    sales: Object,
    purchase: Object,
    inventory: Object,
    accounting: Object,
    payableReceivable: Object,
    periodLabel: String,
});

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

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number ?? 0);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const statusLabels = {
    'draft': 'Draft',
    'quote': 'Penawaran',
    'confirmed': 'Dikonfirmasi',
    'partially_delivered': 'Sebagian Dikirim',
    'delivered': 'Terkirim',
    'closed': 'Selesai',
    'canceled': 'Dibatalkan',
    'cancelled': 'Dibatalkan',
    'posted': 'Diposting',
    'partially_paid': 'Sebagian Dibayar',
    'paid': 'Lunas',
    'approved': 'Disetujui',
    'partially_received': 'Sebagian Diterima',
    'received': 'Diterima',
    'sent': 'Terkirim',
    'processing': 'Diproses',
    'pending': 'Menunggu',
    'open': 'Terbuka',
};

function getStatusLabel(status) {
    return statusLabels[status] || status?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || status;
}

function getStatusClass(status) {
    const classes = {
        'draft': 'bg-gray-100 text-gray-700',
        'quote': 'bg-blue-100 text-blue-700',
        'confirmed': 'bg-indigo-100 text-indigo-700',
        'posted': 'bg-green-100 text-green-700',
        'paid': 'bg-emerald-100 text-emerald-700',
        'approved': 'bg-green-100 text-green-700',
        'sent': 'bg-cyan-100 text-cyan-700',
        'cancelled': 'bg-red-100 text-red-700',
        'canceled': 'bg-red-100 text-red-700',
        'partially_delivered': 'bg-amber-100 text-amber-700',
        'partially_received': 'bg-amber-100 text-amber-700',
        'partially_paid': 'bg-amber-100 text-amber-700',
        'delivered': 'bg-emerald-100 text-emerald-700',
        'received': 'bg-emerald-100 text-emerald-700',
        'closed': 'bg-slate-100 text-slate-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
}

// Sales trend chart
const salesTrendChartData = computed(() => ({
    labels: props.sales?.monthlyTrend?.labels || [],
    datasets: [
        {
            label: props.sales?.monthlyTrend?.datasets?.[0]?.label || 'Sales Order',
            data: props.sales?.monthlyTrend?.datasets?.[0]?.data || [],
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
        },
        {
            label: props.sales?.monthlyTrend?.datasets?.[1]?.label || 'Faktur Penjualan',
            data: props.sales?.monthlyTrend?.datasets?.[1]?.data || [],
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
        },
    ],
}));

// Purchase trend chart
const purchaseTrendChartData = computed(() => ({
    labels: props.purchase?.monthlyTrend?.labels || [],
    datasets: [
        {
            label: props.purchase?.monthlyTrend?.datasets?.[0]?.label || 'Purchase Order',
            data: props.purchase?.monthlyTrend?.datasets?.[0]?.data || [],
            borderColor: 'rgb(139, 92, 246)',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            fill: true,
            tension: 0.4,
        },
        {
            label: props.purchase?.monthlyTrend?.datasets?.[1]?.label || 'Faktur Pembelian',
            data: props.purchase?.monthlyTrend?.datasets?.[1]?.data || [],
            borderColor: 'rgb(245, 158, 11)',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
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
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Dashboard</h2>
        </template>

        <div class="mx-auto space-y-6">
            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-6 text-white shadow-lg">
                <h1 class="text-2xl font-bold mb-1">Selamat Datang, {{ userName }}!</h1>
                <p class="text-blue-100">Ringkasan bisnis Anda untuk {{ periodLabel }}</p>
            </div>

            <!-- ─── Accounting Section ─── -->
            <template v-if="access.accounting && accounting">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Akuntansi</h3>
                    <Link :href="route('operational-reconciliation.index')" class="text-sm text-blue-600 hover:text-blue-800">Detail →</Link>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-blue-800">Pendapatan</h4>
                            <CurrencyDollarIcon class="w-5 h-5 text-blue-600" />
                        </div>
                        <p class="text-xl font-bold text-blue-900">{{ formatCurrency(accounting.revenue) }}</p>
                    </div>

                    <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border border-red-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-red-800">Harga Pokok Penjualan</h4>
                            <ArrowTrendingDownIcon class="w-5 h-5 text-red-600" />
                        </div>
                        <p class="text-xl font-bold text-red-900">{{ formatCurrency(accounting.cogs) }}</p>
                    </div>

                    <div :class="[
                        'rounded-xl p-5 border',
                        accounting.gross_profit >= 0
                            ? 'bg-gradient-to-br from-emerald-50 to-emerald-100 border-emerald-200'
                            : 'bg-gradient-to-br from-red-50 to-red-100 border-red-200'
                    ]">
                        <div class="flex items-center justify-between mb-3">
                            <h4 :class="['text-sm font-semibold', accounting.gross_profit >= 0 ? 'text-emerald-800' : 'text-red-800']">Laba Kotor</h4>
                            <component :is="accounting.gross_profit >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                                :class="['w-5 h-5', accounting.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600']"
                            />
                        </div>
                        <p :class="['text-xl font-bold', accounting.gross_profit >= 0 ? 'text-emerald-900' : 'text-red-900']">
                            {{ formatCurrency(accounting.gross_profit) }}
                        </p>
                        <p :class="['text-xs mt-1', accounting.gross_profit >= 0 ? 'text-emerald-600' : 'text-red-600']">
                            Margin {{ accounting.gross_margin }}%
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-5 border border-orange-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-orange-800">Beban Usaha</h4>
                            <ArrowTrendingDownIcon class="w-5 h-5 text-orange-600" />
                        </div>
                        <p class="text-xl font-bold text-orange-900">{{ formatCurrency(accounting.total_expenses) }}</p>
                        <div class="mt-2 space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-orange-600">Operasional</span>
                                <span class="text-orange-800">{{ formatCurrency(accounting.operational_expenses) }}</span>
                            </div>
                            <div v-if="accounting.depreciation" class="flex justify-between text-xs">
                                <span class="text-orange-600">Penyusutan</span>
                                <span class="text-orange-800">{{ formatCurrency(accounting.depreciation) }}</span>
                            </div>
                            <div v-if="accounting.other_expenses" class="flex justify-between text-xs">
                                <span class="text-orange-600">Lainnya</span>
                                <span class="text-orange-800">{{ formatCurrency(accounting.other_expenses) }}</span>
                            </div>
                        </div>
                    </div>

                    <div :class="[
                        'rounded-xl p-5 border',
                        accounting.net_profit >= 0
                            ? 'bg-gradient-to-br from-green-50 to-green-100 border-green-200'
                            : 'bg-gradient-to-br from-red-50 to-red-100 border-red-200'
                    ]">
                        <div class="flex items-center justify-between mb-3">
                            <h4 :class="['text-sm font-semibold', accounting.net_profit >= 0 ? 'text-green-800' : 'text-red-800']">Laba Bersih</h4>
                            <component :is="accounting.net_profit >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                                :class="['w-5 h-5', accounting.net_profit >= 0 ? 'text-green-600' : 'text-red-600']"
                            />
                        </div>
                        <p :class="['text-xl font-bold', accounting.net_profit >= 0 ? 'text-green-900' : 'text-red-900']">
                            {{ formatCurrency(accounting.net_profit) }}
                        </p>
                        <p :class="['text-xs mt-1', accounting.net_profit >= 0 ? 'text-green-600' : 'text-red-600']">
                            Margin {{ accounting.net_margin }}%
                        </p>
                    </div>

                    <div class="bg-white rounded-xl p-5 border-2 border-indigo-200 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-indigo-800">Total Aset</h4>
                            <BuildingLibraryIcon class="w-5 h-5 text-indigo-600" />
                        </div>
                        <p class="text-xl font-bold text-indigo-900">{{ formatCurrency(accounting.total_assets) }}</p>
                    </div>

                </div>
            </template>

            <!-- ─── Payable / Receivable Section ─── -->
            <template v-if="access.payable_receivable && payableReceivable">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Hutang / Piutang</h3>
                    <Link :href="route('payable-receivable-overview.index')" class="text-sm text-blue-600 hover:text-blue-800">Detail →</Link>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 border border-blue-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-blue-800">Total Piutang</h4>
                            <ArrowTrendingUpIcon class="w-5 h-5 text-blue-600" />
                        </div>
                        <p class="text-xl font-bold text-blue-900">{{ formatCurrency(payableReceivable.total_receivable) }}</p>
                    </div>

                    <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 border border-red-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-red-800">Total Hutang</h4>
                            <ArrowTrendingDownIcon class="w-5 h-5 text-red-600" />
                        </div>
                        <p class="text-xl font-bold text-red-900">{{ formatCurrency(payableReceivable.total_payable) }}</p>
                    </div>

                    <div :class="[
                        'rounded-xl p-5 border',
                        payableReceivable.net_position >= 0
                            ? 'bg-gradient-to-br from-green-50 to-green-100 border-green-200'
                            : 'bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200'
                    ]">
                        <div class="flex items-center justify-between mb-3">
                            <h4 :class="['text-sm font-semibold', payableReceivable.net_position >= 0 ? 'text-green-800' : 'text-orange-800']">Posisi Bersih</h4>
                            <ScaleIcon :class="['w-5 h-5', payableReceivable.net_position >= 0 ? 'text-green-600' : 'text-orange-600']" />
                        </div>
                        <p :class="['text-xl font-bold', payableReceivable.net_position >= 0 ? 'text-green-900' : 'text-orange-900']">
                            {{ formatCurrency(payableReceivable.net_position) }}
                        </p>
                    </div>

                    <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-5 border border-amber-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-amber-800">Jatuh Tempo</h4>
                            <ExclamationTriangleIcon class="w-5 h-5 text-amber-600" />
                        </div>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-amber-700">Piutang</span>
                                <span class="font-semibold text-amber-900">{{ formatCurrency(payableReceivable.receivable_overdue) }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-amber-700">Hutang</span>
                                <span class="font-semibold text-amber-900">{{ formatCurrency(payableReceivable.payable_overdue) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ─── Sales Section ─── -->
            <template v-if="access.sales && sales">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Penjualan</h3>
                    <Link :href="route('sales-reports.index')" class="text-sm text-blue-600 hover:text-blue-800">Detail →</Link>
                </div>
                <!-- Sales Charts & Recent -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Penjualan (6 Bulan)</h3>
                        <div class="h-64">
                            <Line
                                v-if="salesTrendChartData.labels.length > 0"
                                :data="salesTrendChartData"
                                :options="trendOptions"
                            />
                            <div v-else class="h-full flex items-center justify-center text-gray-400">Tidak ada data</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">SO Terbaru</h3>
                            <Link :href="route('sales-orders.index')" class="text-sm text-blue-600 hover:text-blue-800">Semua →</Link>
                        </div>
                        <div class="space-y-3">
                            <div v-for="doc in sales.recentOrders" :key="doc.id"
                                 class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <div>
                                    <Link :href="route('sales-orders.show', doc.id)" class="text-sm font-medium text-gray-900 hover:text-blue-600">
                                        {{ doc.number }}
                                    </Link>
                                    <p class="text-xs text-gray-500">{{ doc.partner || '-' }}</p>
                                </div>
                                <div class="text-right">
                                    <span :class="['px-2 py-1 text-xs rounded-full', getStatusClass(doc.status)]">
                                        {{ getStatusLabel(doc.status) }}
                                    </span>
                                    <p class="text-xs text-gray-500 mt-1">{{ formatDate(doc.date) }}</p>
                                </div>
                            </div>
                            <div v-if="!sales.recentOrders?.length" class="text-center py-4 text-gray-400 text-sm">Tidak ada data</div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ─── Purchase Section ─── -->
            <template v-if="access.purchase && purchase">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Pembelian</h3>
                    <Link :href="route('purchasing-reports.index')" class="text-sm text-blue-600 hover:text-blue-800">Detail →</Link>
                </div>
                <!-- Purchase Charts & Recent -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Pembelian (6 Bulan)</h3>
                        <div class="h-64">
                            <Line
                                v-if="purchaseTrendChartData.labels.length > 0"
                                :data="purchaseTrendChartData"
                                :options="trendOptions"
                            />
                            <div v-else class="h-full flex items-center justify-center text-gray-400">Tidak ada data</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">PO Terbaru</h3>
                            <Link :href="route('purchase-orders.index')" class="text-sm text-blue-600 hover:text-blue-800">Semua →</Link>
                        </div>
                        <div class="space-y-3">
                            <div v-for="doc in purchase.recentOrders" :key="doc.id"
                                 class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <div>
                                    <Link :href="route('purchase-orders.show', doc.id)" class="text-sm font-medium text-gray-900 hover:text-blue-600">
                                        {{ doc.number }}
                                    </Link>
                                    <p class="text-xs text-gray-500">{{ doc.partner || '-' }}</p>
                                </div>
                                <div class="text-right">
                                    <span :class="['px-2 py-1 text-xs rounded-full', getStatusClass(doc.status)]">
                                        {{ getStatusLabel(doc.status) }}
                                    </span>
                                    <p class="text-xs text-gray-500 mt-1">{{ formatDate(doc.date) }}</p>
                                </div>
                            </div>
                            <div v-if="!purchase.recentOrders?.length" class="text-center py-4 text-gray-400 text-sm">Tidak ada data</div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ─── Inventory Section ─── -->
            <template v-if="access.inventory && inventory">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Persediaan</h3>
                    <Link :href="route('inventory-reports.index')" class="text-sm text-blue-600 hover:text-blue-800">Detail →</Link>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-5 border border-teal-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-teal-800">Jumlah Item</h4>
                            <CubeIcon class="w-5 h-5 text-teal-600" />
                        </div>
                        <p class="text-xl font-bold text-teal-900">{{ formatNumber(inventory.total_items) }}</p>
                        <p class="text-xs text-teal-600 mt-1">produk dengan stok > 0</p>
                    </div>

                    <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-5 border border-indigo-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-indigo-800">Nilai Persediaan</h4>
                            <ArchiveBoxIcon class="w-5 h-5 text-indigo-600" />
                        </div>
                        <p class="text-xl font-bold text-indigo-900">{{ formatCurrency(inventory.total_value) }}</p>
                    </div>

                    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-gray-800">Transaksi Periode</h4>
                        </div>
                        <p class="text-xl font-bold text-gray-900">{{ formatNumber(inventory.total_transactions) }}</p>
                        <div v-if="inventory.transactions_by_type && Object.keys(inventory.transactions_by_type).length > 0" class="mt-2 space-y-1">
                            <div v-for="(count, type) in inventory.transactions_by_type" :key="type" class="flex justify-between text-xs">
                                <span class="text-gray-500">{{ type }}</span>
                                <span class="text-gray-700 font-medium">{{ formatNumber(count) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Empty state when no access to anything -->
            <div v-if="!access.sales && !access.purchase && !access.inventory && !access.accounting && !access.payable_receivable"
                 class="bg-white rounded-xl p-12 border border-gray-200 text-center">
                <BanknotesIcon class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                <p class="text-gray-500">Tidak ada modul yang dapat ditampilkan. Hubungi administrator untuk mengatur akses Anda.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
