<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import PurchasingReportTabs from '@/Tabs/PurchasingReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { ShoppingCartIcon, TruckIcon, DocumentTextIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    companies: Array,
    branches: Array,
    filters: Object,
    summaryData: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('purchasing-reports.index'), form.value, {
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
</script>

<template>
    <Head title="Laporan Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Laporan Pembelian</h2>
        </template>

        <div class="mx-auto">
            <PurchasingReportTabs activeTab="purchasing-reports.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <!-- Filters -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <AppSelect
                            v-model="form.company_id"
                            :options="companies.map(company => ({ value: company.id, label: company.name }))"
                            label="Perusahaan"
                            multiple
                            placeholder="Pilih perusahaan"
                        />
                        <AppSelect
                            v-model="form.branch_id"
                            :options="branches.map(branch => ({ value: branch.id, label: branch.name }))"
                            label="Cabang"
                            multiple
                            placeholder="Pilih cabang"
                        />
                        <div class="grid grid-cols-2 gap-4">
                            <AppInput
                                v-model="form.start_date"
                                type="date"
                                label="Dari Tanggal"
                            />
                            <AppInput
                                v-model="form.end_date"
                                type="date"
                                label="Sampai Tanggal"
                            />
                        </div>
                    </div>
                    
                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <!-- Summary Cards -->
                    <div v-if="summaryData" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Purchase Orders Card -->
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-blue-800">Purchase Order</h3>
                                <div class="p-2 bg-blue-200 rounded-lg">
                                    <ShoppingCartIcon class="w-6 h-6 text-blue-700" />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-600">Total</span>
                                    <span class="text-2xl font-bold text-blue-900">{{ formatNumber(summaryData.purchase_orders.total_count) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-blue-600">Nilai Total</span>
                                    <span class="font-medium text-blue-800">{{ formatCurrency(summaryData.purchase_orders.total_value) }}</span>
                                </div>
                                <div class="pt-3 border-t border-blue-200 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-blue-500">Draft</span>
                                        <span class="text-blue-700">{{ formatNumber(summaryData.purchase_orders.draft_count) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-blue-500">Approved</span>
                                        <span class="text-blue-700">{{ formatNumber(summaryData.purchase_orders.approved_count) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-blue-500">Sent</span>
                                        <span class="text-blue-700">{{ formatNumber(summaryData.purchase_orders.sent_count) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Goods Receipts Card -->
                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 border border-green-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-green-800">Penerimaan Barang</h3>
                                <div class="p-2 bg-green-200 rounded-lg">
                                    <TruckIcon class="w-6 h-6 text-green-700" />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-green-600">Total</span>
                                    <span class="text-2xl font-bold text-green-900">{{ formatNumber(summaryData.goods_receipts.total_count) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-green-600">Nilai Total</span>
                                    <span class="font-medium text-green-800">{{ formatCurrency(summaryData.goods_receipts.total_value) }}</span>
                                </div>
                                <div class="pt-3 border-t border-green-200 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-green-500">Draft</span>
                                        <span class="text-green-700">{{ formatNumber(summaryData.goods_receipts.draft_count) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-green-500">Posted</span>
                                        <span class="text-green-700">{{ formatNumber(summaryData.goods_receipts.posted_count) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Invoices Card -->
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 border border-purple-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-purple-800">Faktur Pembelian</h3>
                                <div class="p-2 bg-purple-200 rounded-lg">
                                    <DocumentTextIcon class="w-6 h-6 text-purple-700" />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-purple-600">Total</span>
                                    <span class="text-2xl font-bold text-purple-900">{{ formatNumber(summaryData.purchase_invoices.total_count) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-purple-600">Nilai Total</span>
                                    <span class="font-medium text-purple-800">{{ formatCurrency(summaryData.purchase_invoices.total_value) }}</span>
                                </div>
                                <div class="pt-3 border-t border-purple-200 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-purple-500">Draft</span>
                                        <span class="text-purple-700">{{ formatNumber(summaryData.purchase_invoices.draft_count) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-purple-500">Posted</span>
                                        <span class="text-purple-700">{{ formatNumber(summaryData.purchase_invoices.posted_count) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-purple-500">Paid</span>
                                        <span class="text-purple-700">{{ formatNumber(summaryData.purchase_invoices.paid_count) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Returns Card -->
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-6 border border-orange-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-orange-800">Retur Pembelian</h3>
                                <div class="p-2 bg-orange-200 rounded-lg">
                                    <ArrowUturnLeftIcon class="w-6 h-6 text-orange-700" />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-orange-600">Total</span>
                                    <span class="text-2xl font-bold text-orange-900">{{ formatNumber(summaryData.purchase_returns.total_count) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-orange-600">Nilai Total</span>
                                    <span class="font-medium text-orange-800">{{ formatCurrency(summaryData.purchase_returns.total_value) }}</span>
                                </div>
                                <div class="pt-3 border-t border-orange-200 space-y-1">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-orange-500">Draft</span>
                                        <span class="text-orange-700">{{ formatNumber(summaryData.purchase_returns.draft_count) }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-orange-500">Posted</span>
                                        <span class="text-orange-700">{{ formatNumber(summaryData.purchase_returns.posted_count) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-12 text-gray-500">
                        <ShoppingCartIcon class="w-16 h-16 mx-auto mb-4 text-gray-300" />
                        <p>Pilih filter dan klik "Tampilkan Laporan" untuk melihat ringkasan pembelian.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
