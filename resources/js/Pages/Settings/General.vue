<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import {
    Cog8ToothIcon,
    ChartBarIcon,
    DocumentTextIcon,
    ShoppingCartIcon,
    ClipboardDocumentListIcon,
    BanknotesIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    dashboardPreferences: Object,
});

const form = useForm({
    dashboard_preferences: {
        default_period: props.dashboardPreferences?.default_period || 'month',
        visible_cards: props.dashboardPreferences?.visible_cards || {
            sales_orders: true,
            sales_invoices: true,
            purchase_orders: true,
            purchase_invoices: true,
            receivables: true,
            payables: true,
        },
        show_charts: props.dashboardPreferences?.show_charts ?? true,
        show_recent_documents: props.dashboardPreferences?.show_recent_documents ?? true,
    },
});

const periodOptions = [
    { value: 'week', label: 'Minggu Ini' },
    { value: 'month', label: 'Bulan Ini' },
    { value: 'quarter', label: 'Kuartal Ini' },
    { value: 'year', label: 'Tahun Ini' },
];

const cardOptions = [
    { key: 'sales_orders', label: 'Sales Order', icon: ShoppingCartIcon, color: 'text-green-600' },
    { key: 'sales_invoices', label: 'Faktur Penjualan', icon: DocumentTextIcon, color: 'text-blue-600' },
    { key: 'purchase_orders', label: 'Purchase Order', icon: ClipboardDocumentListIcon, color: 'text-purple-600' },
    { key: 'purchase_invoices', label: 'Faktur Pembelian', icon: BanknotesIcon, color: 'text-orange-600' },
    { key: 'receivables', label: 'Piutang', icon: ArrowTrendingUpIcon, color: 'text-emerald-600' },
    { key: 'payables', label: 'Hutang', icon: ArrowTrendingDownIcon, color: 'text-red-600' },
];

function submit() {
    form.put(route('general-settings.update'), {
        preserveScroll: true,
    });
}

function toggleCard(key) {
    form.dashboard_preferences.visible_cards[key] = !form.dashboard_preferences.visible_cards[key];
}
</script>

<template>
    <Head title="Pengaturan Umum" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Umum</h2>
        </template>

        <div class="mx-auto max-w-4xl">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Dashboard Preferences Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600">
                        <div class="flex items-center space-x-3">
                            <ChartBarIcon class="w-6 h-6 text-white" />
                            <h3 class="text-lg font-semibold text-white">Preferensi Dashboard</h3>
                        </div>
                        <p class="text-blue-100 text-sm mt-1">Atur tampilan dan data yang ditampilkan di halaman dashboard</p>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Default Period -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Periode Default
                            </label>
                            <p class="text-sm text-gray-500 mb-3">
                                Pilih rentang waktu default untuk data yang ditampilkan di dashboard
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <button
                                    v-for="option in periodOptions"
                                    :key="option.value"
                                    type="button"
                                    @click="form.dashboard_preferences.default_period = option.value"
                                    :class="[
                                        'px-4 py-3 rounded-lg border-2 text-sm font-medium transition-all',
                                        form.dashboard_preferences.default_period === option.value
                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                            : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                                    ]"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Visible Cards -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Kartu KPI yang Ditampilkan
                            </label>
                            <p class="text-sm text-gray-500 mb-3">
                                Pilih metrik bisnis yang ingin Anda lihat di dashboard
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <button
                                    v-for="card in cardOptions"
                                    :key="card.key"
                                    type="button"
                                    @click="toggleCard(card.key)"
                                    :class="[
                                        'flex items-center space-x-3 px-4 py-3 rounded-lg border-2 text-sm font-medium transition-all',
                                        form.dashboard_preferences.visible_cards[card.key]
                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                            : 'border-gray-200 bg-white text-gray-400 hover:border-gray-300'
                                    ]"
                                >
                                    <component :is="card.icon" :class="['w-5 h-5', form.dashboard_preferences.visible_cards[card.key] ? card.color : 'text-gray-400']" />
                                    <span>{{ card.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Show Charts -->
                        <div class="flex items-center justify-between py-4 border-t border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Tampilkan Grafik
                                </label>
                                <p class="text-sm text-gray-500">
                                    Menampilkan grafik tren penjualan dan distribusi status
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="form.dashboard_preferences.show_charts = !form.dashboard_preferences.show_charts"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    form.dashboard_preferences.show_charts ? 'bg-blue-600' : 'bg-gray-200'
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        form.dashboard_preferences.show_charts ? 'translate-x-5' : 'translate-x-0'
                                    ]"
                                />
                            </button>
                        </div>

                        <!-- Show Recent Documents -->
                        <div class="flex items-center justify-between py-4 border-t border-gray-100">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Tampilkan Dokumen Terbaru
                                </label>
                                <p class="text-sm text-gray-500">
                                    Menampilkan daftar dokumen terbaru (SO, SI, PO)
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="form.dashboard_preferences.show_recent_documents = !form.dashboard_preferences.show_recent_documents"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                                    form.dashboard_preferences.show_recent_documents ? 'bg-blue-600' : 'bg-gray-200'
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        form.dashboard_preferences.show_recent_documents ? 'translate-x-5' : 'translate-x-0'
                                    ]"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Error Display -->
                <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h4 class="text-red-800 font-medium mb-2">Terjadi kesalahan:</h4>
                    <ul class="list-disc list-inside text-red-600 text-sm">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <AppPrimaryButton type="submit" :disabled="form.processing">
                        <span v-if="form.processing">Menyimpan...</span>
                        <span v-else>Simpan Pengaturan</span>
                    </AppPrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
