<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    allocation: Object,
    filters: Object,
});

const ruleLabels = {
    'revenue_based': 'Berdasarkan Revenue',
    'quantity_based': 'Berdasarkan Quantity',
    'time_based': 'Berdasarkan Waktu',
    'manual': 'Manual'
};

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID');
}
</script>

<template>
    <Head :title="`Alokasi #${allocation.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Alokasi Biaya</p>
                    <h2 class="text-2xl font-semibold">
                        Alokasi #{{ allocation.id }}
                    </h2>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                    {{ allocation.period }}
                </span>
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <AppBackLink :href="route('costing.cost-allocations.index', filters)" text="Kembali ke Daftar Alokasi" />
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Alokasi</h3>
                                <p><span class="text-gray-500 text-sm">Pool Biaya:</span> {{ allocation.cost_pool?.name }}</p>
                                <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ allocation.cost_pool?.company?.name || '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Periode:</span> {{ allocation.period }}</p>
                                <p><span class="text-gray-500 text-sm">Aturan:</span> {{ ruleLabels[allocation.allocation_rule] || allocation.allocation_rule }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Target Faktur</h3>
                                <p><span class="text-gray-500 text-sm">No. Faktur:</span> {{ allocation.sales_invoice_line?.sales_invoice?.invoice_number || '—' }}</p>
                                <p><span class="text-gray-500 text-sm">Pelanggan:</span> {{ allocation.sales_invoice_line?.sales_invoice?.partner?.name || '—' }}</p>
                                <p v-if="allocation.sales_invoice_line?.product"><span class="text-gray-500 text-sm">Produk:</span> {{ allocation.sales_invoice_line?.product?.name }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Detail Jumlah</h3>
                                <p class="flex justify-between text-sm">
                                    <span>Jumlah Dialokasikan</span>
                                    <span class="font-medium text-lg">{{ formatNumber(allocation.amount) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Numerator</span>
                                    <span>{{ formatNumber(allocation.numerator) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Denominator</span>
                                    <span>{{ formatNumber(allocation.denominator) }}</span>
                                </p>
                                <p class="flex justify-between text-sm border-t pt-2">
                                    <span>Rasio Alokasi</span>
                                    <span class="font-medium">{{ allocation.allocation_ratio ? (allocation.allocation_ratio * 100).toFixed(2) + '%' : '—' }}</span>
                                </p>
                            </div>
                        </div>

                        <div v-if="allocation.notes" class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                            <p class="text-gray-700 whitespace-pre-line">{{ allocation.notes }}</p>
                        </div>

                        <div class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Informasi Sistem</h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <p><span class="text-gray-500">Dibuat oleh:</span> {{ allocation.creator?.name || '—' }}</p>
                                <p><span class="text-gray-500">Tanggal Dibuat:</span> {{ formatDate(allocation.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
