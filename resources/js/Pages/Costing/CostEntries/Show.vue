<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    costEntry: Object,
    filters: Object,
});

const sourceTypeLabels = {
    'purchase_invoice': 'Faktur Pembelian',
    'expense_claim': 'Klaim Biaya', 
    'journal': 'Jurnal',
    'payroll': 'Payroll'
};

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID');
}
</script>

<template>
    <Head :title="`Catatan Biaya #${costEntry.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Catatan Biaya</p>
                    <h2 class="text-2xl font-semibold">
                        #{{ costEntry.id }}
                    </h2>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                    {{ sourceTypeLabels[costEntry.source_type] || costEntry.source_type }}
                </span>
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <AppBackLink :href="route('costing.cost-entries.index', filters)" text="Kembali ke Daftar Catatan Biaya" />
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Dokumen</h3>
                                <p><span class="text-gray-500 text-sm">Tanggal:</span> {{ formatDate(costEntry.cost_date) }}</p>
                                <p><span class="text-gray-500 text-sm">Sumber:</span> {{ sourceTypeLabels[costEntry.source_type] || costEntry.source_type }}</p>
                                <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ costEntry.company?.name || '—' }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Produk</h3>
                                <p><span class="text-gray-500 text-sm">Produk:</span> {{ costEntry.product?.name || '—' }}</p>
                                <p v-if="costEntry.product_variant"><span class="text-gray-500 text-sm">Varian:</span> {{ costEntry.product_variant?.sku || '—' }}</p>
                                <p v-if="costEntry.cost_pool"><span class="text-gray-500 text-sm">Pool Biaya:</span> {{ costEntry.cost_pool?.name }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Jumlah</h3>
                                <p class="flex justify-between text-sm">
                                    <span>Mata Uang</span>
                                    <span class="font-medium">{{ costEntry.currency?.code || '—' }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Jumlah</span>
                                    <span class="font-medium">{{ formatNumber(costEntry.amount) }}</span>
                                </p>
                                <p v-if="costEntry.exchange_rate != 1" class="flex justify-between text-sm">
                                    <span>Kurs</span>
                                    <span>{{ formatNumber(costEntry.exchange_rate) }}</span>
                                </p>
                                <p class="flex justify-between text-sm border-t pt-2">
                                    <span>Jumlah (Base)</span>
                                    <span class="font-medium text-lg">{{ formatNumber(costEntry.amount_base) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Teralokasi</span>
                                    <span :class="costEntry.allocated_amount > 0 ? 'text-green-600' : 'text-gray-400'">
                                        {{ formatNumber(costEntry.allocated_amount) }}
                                    </span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Sisa</span>
                                    <span :class="(costEntry.amount_base - costEntry.allocated_amount) > 0 ? 'text-orange-600 font-medium' : 'text-green-600'">
                                        {{ formatNumber(costEntry.amount_base - costEntry.allocated_amount) }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div v-if="costEntry.description || costEntry.notes" class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                            <p v-if="costEntry.description" class="text-gray-700">{{ costEntry.description }}</p>
                            <p v-if="costEntry.notes" class="text-gray-500 text-sm mt-2">{{ costEntry.notes }}</p>
                        </div>

                        <div v-if="costEntry.invoice_detail_costs?.length" class="bg-white border border-gray-200 rounded">
                            <h3 class="text-sm font-semibold text-gray-600 p-4 border-b">Alokasi ke Faktur</h3>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">#</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">No. Faktur</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(detailCost, index) in costEntry.invoice_detail_costs" :key="detailCost.id">
                                        <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
                                        <td class="px-4 py-3">
                                            {{ detailCost.sales_invoice_line?.sales_invoice?.invoice_number || '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(detailCost.amount_base) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
