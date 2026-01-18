<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ShoppingCartIcon,
    DocumentTextIcon,
    ClipboardDocumentListIcon,
    BanknotesIcon,
    CreditCardIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
} from '@heroicons/vue/24/outline';
import { Line, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
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
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    userName: String,
    preferences: Object,
    summary: Object,
    chartData: Object,
    recentDocuments: Object,
});

// Visible cards based on user preferences
const visibleCards = computed(() => props.preferences?.visible_cards || {
    sales_orders: true,
    sales_invoices: true,
    purchase_orders: true,
    purchase_invoices: true,
    receivables: true,
    payables: true,
});

const page = usePage();
const canViewSalesOrders = computed(() => (page.props.auth?.can?.viewSalesOrders ?? true) && visibleCards.value.sales_orders);
const canViewSalesInvoices = computed(() => (page.props.auth?.can?.viewSalesInvoices ?? true) && visibleCards.value.sales_invoices);
const canViewPurchaseOrders = computed(() => (page.props.auth?.can?.viewPurchaseOrders ?? true) && visibleCards.value.purchase_orders);
const canViewPurchaseInvoices = computed(() => (page.props.auth?.can?.viewPurchaseInvoices ?? true) && visibleCards.value.purchase_invoices);
const showReceivables = computed(() => visibleCards.value.receivables);
const showPayables = computed(() => visibleCards.value.payables);

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

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

// Chart configurations
const monthlyTrendChartData = computed(() => ({
    labels: props.chartData?.monthlyTrend?.labels || [],
    datasets: props.chartData?.monthlyTrend?.datasets || [],
}));

const monthlyTrendChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            labels: {
                usePointStyle: true,
                padding: 15,
            },
        },
        tooltip: {
            callbacks: {
                label: (context) => `${context.dataset.label}: ${formatCurrency(context.raw)}`,
            },
        },
    },
    scales: {
        y: {
            ticks: {
                callback: (value) => formatCurrency(value),
            },
        },
    },
};

const soStatusChartData = computed(() => ({
    labels: props.chartData?.salesOrderStatus?.labels?.map(l => getStatusLabel(l)) || [],
    datasets: [{
        data: props.chartData?.salesOrderStatus?.data || [],
        backgroundColor: [
            'rgba(156, 163, 175, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(99, 102, 241, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(239, 68, 68, 0.8)',
        ],
        borderWidth: 0,
    }],
}));

const siStatusChartData = computed(() => ({
    labels: props.chartData?.salesInvoiceStatus?.labels?.map(l => getStatusLabel(l)) || [],
    datasets: [{
        data: props.chartData?.salesInvoiceStatus?.data || [],
        backgroundColor: [
            'rgba(156, 163, 175, 0.8)',
            'rgba(59, 130, 246, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(16, 185, 129, 0.8)',
            'rgba(239, 68, 68, 0.8)',
        ],
        borderWidth: 0,
    }],
}));

const statusChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%',
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 15,
                font: { size: 11 },
            },
        },
    },
};

// Status labels mapping for nice display names
const statusLabels = {
    // Sales Order statuses
    'draft': 'Draft',
    'quote': 'Penawaran',
    'confirmed': 'Dikonfirmasi',
    'partially_delivered': 'Sebagian Dikirim',
    'delivered': 'Terkirim',
    'closed': 'Selesai',
    'canceled': 'Dibatalkan',
    'cancelled': 'Dibatalkan',
    // Invoice statuses
    'posted': 'Diposting',
    'partially_paid': 'Sebagian Dibayar',
    'paid': 'Lunas',
    // Purchase Order statuses
    'approved': 'Disetujui',
    'partially_received': 'Sebagian Diterima',
    'received': 'Diterima',
    'sent': 'Terkirim',
    // Other
    'processing': 'Diproses',
    'pending': 'Menunggu',
    'open': 'Terbuka',
    'partial': 'Sebagian',
};

function getStatusLabel(status) {
    return statusLabels[status] || status?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || status;
}

function getStatusClass(status) {
    const classes = {
        'draft': 'bg-gray-100 text-gray-700',
        'quote': 'bg-blue-100 text-blue-700',
        'confirmed': 'bg-indigo-100 text-indigo-700',
        'processing': 'bg-yellow-100 text-yellow-700',
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
                <p class="text-blue-100">Ringkasan bisnis Anda untuk {{ summary?.periodLabel || 'bulan ini' }}</p>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Sales Orders Card -->
                <Link v-if="canViewSalesOrders" :href="route('sales-orders.index')" 
                      class="block bg-white rounded-xl p-6 border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Sales Order</h3>
                        <div class="p-2 bg-green-100 rounded-lg">
                            <ShoppingCartIcon class="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-3xl font-bold text-gray-900">{{ formatNumber(summary?.salesOrders?.count) }}</span>
                        <span class="text-sm text-gray-500">dokumen</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-600">
                        Total: <span class="font-medium text-green-600">{{ formatCurrency(summary?.salesOrders?.total) }}</span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">
                        {{ formatNumber(summary?.salesOrders?.confirmed) }} confirmed
                    </div>
                </Link>

                <!-- Sales Invoices Card -->
                <Link v-if="canViewSalesInvoices" :href="route('sales-invoices.index')" 
                      class="block bg-white rounded-xl p-6 border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Faktur Penjualan</h3>
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <DocumentTextIcon class="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-3xl font-bold text-gray-900">{{ formatNumber(summary?.salesInvoices?.count) }}</span>
                        <span class="text-sm text-gray-500">dokumen</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-600">
                        Total: <span class="font-medium text-blue-600">{{ formatCurrency(summary?.salesInvoices?.total) }}</span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">
                        {{ formatNumber(summary?.salesInvoices?.draft) }} draft, {{ formatNumber(summary?.salesInvoices?.posted) }} posted
                    </div>
                </Link>

                <!-- Purchase Orders Card -->
                <Link v-if="canViewPurchaseOrders" :href="route('purchase-orders.index')" 
                      class="block bg-white rounded-xl p-6 border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Purchase Order</h3>
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <ClipboardDocumentListIcon class="w-6 h-6 text-purple-600" />
                        </div>
                    </div>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-3xl font-bold text-gray-900">{{ formatNumber(summary?.purchaseOrders?.count) }}</span>
                        <span class="text-sm text-gray-500">dokumen</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-600">
                        Total: <span class="font-medium text-purple-600">{{ formatCurrency(summary?.purchaseOrders?.total) }}</span>
                    </div>
                    <div class="mt-2 text-xs text-gray-500">
                        {{ formatNumber(summary?.purchaseOrders?.pending) }} pending
                    </div>
                </Link>

                <!-- Receivables Card -->
                <div v-if="showReceivables" class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Piutang</h3>
                        <div class="p-2 bg-emerald-100 rounded-lg">
                            <ArrowTrendingUpIcon class="w-6 h-6 text-emerald-600" />
                        </div>
                    </div>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-2xl font-bold text-emerald-600">{{ formatCurrency(summary?.receivables?.outstanding) }}</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-500">
                        Outstanding dari total <span class="font-medium">{{ formatCurrency(summary?.receivables?.total) }}</span>
                    </div>
                </div>

                <!-- Payables Card -->
                <div v-if="showPayables" class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Hutang</h3>
                        <div class="p-2 bg-red-100 rounded-lg">
                            <ArrowTrendingDownIcon class="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-2xl font-bold text-red-600">{{ formatCurrency(summary?.payables?.outstanding) }}</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-500">
                        Outstanding dari total <span class="font-medium">{{ formatCurrency(summary?.payables?.total) }}</span>
                    </div>
                </div>

                <!-- Purchase Invoices Card -->
                <Link v-if="canViewPurchaseInvoices" :href="route('purchase-invoices.index')" 
                      class="block bg-white rounded-xl p-6 border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Faktur Pembelian</h3>
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <BanknotesIcon class="w-6 h-6 text-orange-600" />
                        </div>
                    </div>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-3xl font-bold text-gray-900">{{ formatNumber(summary?.purchaseInvoices?.count) }}</span>
                        <span class="text-sm text-gray-500">dokumen</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-600">
                        Total: <span class="font-medium text-orange-600">{{ formatCurrency(summary?.purchaseInvoices?.total) }}</span>
                    </div>
                </Link>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Monthly Trend Chart -->
                <div class="lg:col-span-2 bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Tren Penjualan Tahunan</h3>
                    <div class="h-72">
                        <Line 
                            v-if="monthlyTrendChartData.labels?.length > 0"
                            :data="monthlyTrendChartData" 
                            :options="monthlyTrendChartOptions" 
                        />
                        <div v-else class="h-full flex items-center justify-center text-gray-400">
                            Tidak ada data untuk ditampilkan
                        </div>
                    </div>
                </div>

                <!-- Status Charts -->
                <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Status Sales Order</h3>
                    <div class="h-64">
                        <Doughnut 
                            v-if="soStatusChartData.labels?.length > 0"
                            :data="soStatusChartData" 
                            :options="statusChartOptions" 
                        />
                        <div v-else class="h-full flex items-center justify-center text-gray-400">
                            Tidak ada data
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Documents Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Sales Orders -->
                <div v-if="canViewSalesOrders" class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Sales Order Terbaru</h3>
                        <Link :href="route('sales-orders.index')" class="text-sm text-blue-600 hover:text-blue-800">
                            Lihat Semua →
                        </Link>
                    </div>
                    <div class="space-y-3">
                        <div v-for="doc in recentDocuments?.salesOrders" :key="doc.id" 
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
                        <div v-if="!recentDocuments?.salesOrders?.length" class="text-center py-4 text-gray-400 text-sm">
                            Tidak ada data
                        </div>
                    </div>
                </div>

                <!-- Recent Sales Invoices -->
                <div v-if="canViewSalesInvoices" class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Faktur Penjualan Terbaru</h3>
                        <Link :href="route('sales-invoices.index')" class="text-sm text-blue-600 hover:text-blue-800">
                            Lihat Semua →
                        </Link>
                    </div>
                    <div class="space-y-3">
                        <div v-for="doc in recentDocuments?.salesInvoices" :key="doc.id" 
                             class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <div>
                                <Link :href="route('sales-invoices.show', doc.id)" class="text-sm font-medium text-gray-900 hover:text-blue-600">
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
                        <div v-if="!recentDocuments?.salesInvoices?.length" class="text-center py-4 text-gray-400 text-sm">
                            Tidak ada data
                        </div>
                    </div>
                </div>

                <!-- Recent Purchase Orders -->
                <div v-if="canViewPurchaseOrders" class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Purchase Order Terbaru</h3>
                        <Link :href="route('purchase-orders.index')" class="text-sm text-blue-600 hover:text-blue-800">
                            Lihat Semua →
                        </Link>
                    </div>
                    <div class="space-y-3">
                        <div v-for="doc in recentDocuments?.purchaseOrders" :key="doc.id" 
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
                        <div v-if="!recentDocuments?.purchaseOrders?.length" class="text-center py-4 text-gray-400 text-sm">
                            Tidak ada data
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
