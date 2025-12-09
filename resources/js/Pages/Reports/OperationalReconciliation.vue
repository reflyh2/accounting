<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AccountingReportTabs from '@/Tabs/AccountingReportTabs.vue';

const props = defineProps({
    companies: Array,
    branches: Array,
    filters: Object,
    kpis: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

function generateReport() {
    router.get(route('operational-reconciliation.index'), form.value, {
        preserveState: true,
        preserveScroll: true,
    });
}

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
}

function formatCurrency(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(number);
}
</script>

<template>
    <Head title="Rekonsiliasi Operasional" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Rekonsiliasi Operasional & KPI</h2>
        </template>

        <div class="mx-auto">
            <AccountingReportTabs activeTab="operational-reconciliation.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
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
                    <div class="mb-4">
                        <AppPrimaryButton @click="generateReport">
                            Generate Report
                        </AppPrimaryButton>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        <!-- GRNI Aging -->
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <h3 class="text-sm font-semibold text-blue-800 mb-2">GRNI Aging</h3>
                            <p class="text-2xl font-bold text-blue-900">{{ formatCurrency(kpis.grni_aging.total_value) }}</p>
                            <p class="text-xs text-blue-700 mt-1">{{ kpis.grni_aging.total_count }} items outstanding</p>
                        </div>

                        <!-- COGS vs Revenue -->
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <h3 class="text-sm font-semibold text-green-800 mb-2">Margin (COGS vs Revenue)</h3>
                            <p class="text-2xl font-bold text-green-900">{{ formatCurrency(kpis.cogs_vs_revenue.margin) }}</p>
                            <p class="text-xs text-green-700 mt-1">{{ formatNumber(kpis.cogs_vs_revenue.margin_percentage) }}% margin</p>
                        </div>

                        <!-- WO Lead Time -->
                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                            <h3 class="text-sm font-semibold text-purple-800 mb-2">WO Lead Time (Days)</h3>
                            <p class="text-2xl font-bold text-purple-900">{{ formatNumber(kpis.wo_lead_time.average) }}</p>
                            <p class="text-xs text-purple-700 mt-1">{{ kpis.wo_lead_time.count }} completed WOs</p>
                        </div>

                        <!-- Purchase Cycle Time -->
                        <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                            <h3 class="text-sm font-semibold text-yellow-800 mb-2">Purchase Cycle (PO→GRN)</h3>
                            <p class="text-2xl font-bold text-yellow-900">{{ formatNumber(kpis.purchase_cycle_time.average) }}</p>
                            <p class="text-xs text-yellow-700 mt-1">days average</p>
                        </div>

                        <!-- GRN to AP Lag -->
                        <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                            <h3 class="text-sm font-semibold text-orange-800 mb-2">GRN→AP Lag (Days)</h3>
                            <p class="text-2xl font-bold text-orange-900">{{ formatNumber(kpis.grn_to_ap_lag.average) }}</p>
                            <p class="text-xs text-orange-700 mt-1">{{ kpis.grn_to_ap_lag.count }} invoices</p>
                        </div>

                        <!-- PPV Totals -->
                        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                            <h3 class="text-sm font-semibold text-red-800 mb-2">Purchase Price Variance</h3>
                            <p class="text-2xl font-bold text-red-900">{{ formatCurrency(kpis.ppv_totals.net) }}</p>
                            <p class="text-xs text-red-700 mt-1">Net PPV</p>
                        </div>

                        <!-- Sales Fill Rate -->
                        <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                            <h3 class="text-sm font-semibold text-indigo-800 mb-2">Sales Fill Rate</h3>
                            <p class="text-2xl font-bold text-indigo-900">{{ formatNumber(kpis.sales_fill_rate.fill_rate) }}%</p>
                            <p class="text-xs text-indigo-700 mt-1">{{ formatCurrency(kpis.sales_fill_rate.total_delivered) }} delivered</p>
                        </div>

                        <!-- On-Time Delivery -->
                        <div class="bg-teal-50 p-4 rounded-lg border border-teal-200">
                            <h3 class="text-sm font-semibold text-teal-800 mb-2">On-Time Delivery</h3>
                            <p class="text-2xl font-bold text-teal-900">{{ formatNumber(kpis.on_time_delivery.on_time_percentage) }}%</p>
                            <p class="text-xs text-teal-700 mt-1">{{ kpis.on_time_delivery.on_time }}/{{ kpis.on_time_delivery.total }} on time</p>
                        </div>

                        <!-- FG Unit Cost Trend -->
                        <div class="bg-pink-50 p-4 rounded-lg border border-pink-200">
                            <h3 class="text-sm font-semibold text-pink-800 mb-2">FG Unit Cost (Avg)</h3>
                            <p class="text-2xl font-bold text-pink-900">{{ formatCurrency(kpis.fg_unit_cost_trend.current_average) }}</p>
                            <p class="text-xs text-pink-700 mt-1">Current period average</p>
                        </div>
                    </div>

                    <!-- Detailed Tables -->
                    <div class="space-y-6">
                        <!-- GRNI Aging Detail -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">GRNI Aging Detail</h3>
                            <table class="min-w-full border-collapse border border-gray-300">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-300 px-4 py-2 text-left">Age Bucket</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">Count</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(value, key) in kpis.grni_aging.summary" :key="key">
                                        <td class="border border-gray-300 px-4 py-2">{{ key }} days</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ value.count }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatCurrency(value.value) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-100 font-semibold">
                                        <td class="border border-gray-300 px-4 py-2">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ kpis.grni_aging.total_count }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatCurrency(kpis.grni_aging.total_value) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- COGS vs Revenue Detail -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">COGS vs Revenue</h3>
                            <table class="min-w-full border-collapse border border-gray-300">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-300 px-4 py-2 text-left">Metric</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2">Total Revenue</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatCurrency(kpis.cogs_vs_revenue.total_revenue) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2">Total COGS</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatCurrency(kpis.cogs_vs_revenue.total_cogs) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td class="border border-gray-300 px-4 py-2">Gross Margin</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatCurrency(kpis.cogs_vs_revenue.margin) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td class="border border-gray-300 px-4 py-2">Margin %</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(kpis.cogs_vs_revenue.margin_percentage) }}%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- PPV Totals Detail -->
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4">Purchase Price Variance</h3>
                            <table class="min-w-full border-collapse border border-gray-300">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="border border-gray-300 px-4 py-2 text-left">Type</th>
                                        <th class="border border-gray-300 px-4 py-2 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2">Favorable</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right text-green-600">{{ formatCurrency(kpis.ppv_totals.favorable) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2">Unfavorable</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right text-red-600">{{ formatCurrency(kpis.ppv_totals.unfavorable) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td class="border border-gray-300 px-4 py-2">Net PPV</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right" :class="kpis.ppv_totals.net >= 0 ? 'text-red-600' : 'text-green-600'">
                                            {{ formatCurrency(kpis.ppv_totals.net) }}
                                        </td>
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

