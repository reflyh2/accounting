<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import PurchasingReportTabs from '@/Tabs/PurchasingReportTabs.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    companies: Array,
    branches: Array,
    statusOptions: Array,
    groupingOptions: Array,
    filters: Object,
    data: [Object, Array],
    totals: Object,
    statusLabels: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    status: props.filters.status || '',
    group_by: props.filters.group_by || 'document',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
});

const currentGroupBy = computed(() => props.filters.group_by || 'document');
const isGrouped = computed(() => currentGroupBy.value !== 'document');
const isItemsGrouping = computed(() => currentGroupBy.value === 'items');

function generateReport() {
    router.get(route('purchasing-reports.plans'), form.value, {
        preserveState: false,
        preserveScroll: true,
    });
}

function formatNumber(number) {
    return new Intl.NumberFormat('id-ID').format(number ?? 0);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('id-ID');
}

function getStatusLabel(status) {
    return props.statusLabels?.[status] || status;
}

function getStatusBadgeClass(status) {
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        confirmed: 'bg-blue-100 text-blue-800',
        closed: 'bg-purple-100 text-purple-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

function getCompanyName(plan) {
    return plan.branch?.branch_group?.company?.name || '-';
}
</script>

<template>
    <Head title="Laporan Rencana Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Laporan Rencana Pembelian</h2>
        </template>

        <div class="mx-auto">
            <PurchasingReportTabs activeTab="purchasing-reports.plans" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <!-- Filters -->
                    <div class="grid grid-cols-4 gap-4 mb-4">
                        <AppSelect
                            v-model="form.company_id"
                            :options="companies.map(c => ({ value: c.id, label: c.name }))"
                            label="Perusahaan"
                            multiple
                            placeholder="Semua Perusahaan"
                        />
                        <AppSelect
                            v-model="form.branch_id"
                            :options="branches.map(b => ({ value: b.id, label: b.name }))"
                            label="Cabang"
                            multiple
                            placeholder="Semua Cabang"
                        />
                        <AppSelect
                            v-model="form.status"
                            :options="statusOptions"
                            label="Status"
                            placeholder="Semua Status"
                        />
                        <AppSelect
                            v-model="form.group_by"
                            :options="groupingOptions"
                            label="Kelompokkan"
                        />
                        <AppInput v-model="form.start_date" type="date" label="Dari Tanggal" />
                        <AppInput v-model="form.end_date" type="date" label="Sampai Tanggal" />
                    </div>
                    
                    <div class="flex items-center mb-6">
                        <AppPrimaryButton @click="generateReport">
                            Tampilkan Laporan
                        </AppPrimaryButton>
                    </div>

                    <!-- Summary -->
                    <div v-if="totals" class="mb-4 p-4 bg-blue-50 rounded-lg flex gap-8">
                        <div>
                            <span class="text-sm text-blue-600">Total Rencana:</span>
                            <span class="ml-2 font-bold text-blue-900">{{ formatNumber(totals.count) }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-blue-600">Total Item:</span>
                            <span class="ml-2 font-bold text-blue-900">{{ formatNumber(totals.total_planned_qty) }}</span>
                        </div>
                    </div>

                    <!-- Items Grouped View -->
                    <template v-if="isItemsGrouping && Array.isArray(data)">
                        <div class="space-y-4">
                            <div v-for="(group, index) in data" :key="index" class="border rounded-lg overflow-hidden">
                                <div class="bg-gray-100 px-4 py-3 flex justify-between items-center">
                                    <h3 class="font-semibold text-gray-800">{{ group.group_name }}</h3>
                                    <div class="flex gap-4 text-sm">
                                        <span class="text-gray-600">Qty: <strong>{{ formatNumber(group.total_quantity) }}</strong></span>
                                    </div>
                                </div>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">No. Rencana</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Varian</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty Rencana</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty Dipesan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(item, idx) in group.items" :key="idx" class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm">
                                                <Link :href="route('purchase-plans.show', item.document.id)" class="text-blue-600 hover:underline">
                                                    {{ item.document.plan_number }}
                                                </Link>
                                            </td>
                                            <td class="px-4 py-2 text-sm">{{ formatDate(item.document.plan_date) }}</td>
                                            <td class="px-4 py-2 text-sm">{{ item.variant_name || '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-right">{{ formatNumber(item.quantity) }}</td>
                                            <td class="px-4 py-2 text-sm text-right">{{ formatNumber(item.line?.ordered_qty ?? 0) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <!-- Regular Grouped View -->
                    <template v-else-if="isGrouped && Array.isArray(data)">
                        <div class="space-y-4">
                            <div v-for="(group, index) in data" :key="index" class="border rounded-lg overflow-hidden">
                                <div class="bg-gray-100 px-4 py-3 flex justify-between items-center">
                                    <h3 class="font-semibold text-gray-800">
                                        {{ currentGroupBy === 'status' ? getStatusLabel(group.group_name) : group.group_name }}
                                    </h3>
                                    <div class="flex gap-4 text-sm">
                                        <span class="text-gray-600">Jumlah: <strong>{{ formatNumber(group.count) }}</strong></span>
                                    </div>
                                </div>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">No. Rencana</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cabang</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Jumlah Item</th>
                                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in group.items" :key="item.id" class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm">
                                                <Link :href="route('purchase-plans.show', item.id)" class="text-blue-600 hover:underline">
                                                    {{ item.plan_number }}
                                                </Link>
                                            </td>
                                            <td class="px-4 py-2 text-sm">{{ formatDate(item.plan_date) }}</td>
                                            <td class="px-4 py-2 text-sm">{{ item.branch?.name || '-' }}</td>
                                            <td class="px-4 py-2 text-sm text-right">{{ item.lines?.length || 0 }}</td>
                                            <td class="px-4 py-2 text-sm text-center">
                                                <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadgeClass(item.status)]">
                                                    {{ getStatusLabel(item.status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <!-- Document View (Default) -->
                    <template v-else>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Rencana</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal Rencana</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tgl Dibutuhkan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cabang</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perusahaan</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Item</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in data.data" :key="item.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm">
                                            <Link :href="route('purchase-plans.show', item.id)" class="text-blue-600 hover:underline">
                                                {{ item.plan_number }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-3 text-sm">{{ formatDate(item.plan_date) }}</td>
                                        <td class="px-4 py-3 text-sm">{{ formatDate(item.required_date) }}</td>
                                        <td class="px-4 py-3 text-sm">{{ item.branch?.name || '-' }}</td>
                                        <td class="px-4 py-3 text-sm">{{ getCompanyName(item) }}</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ item.lines?.length || 0 }}</td>
                                        <td class="px-4 py-3 text-sm text-center">
                                            <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadgeClass(item.status)]">
                                                {{ getStatusLabel(item.status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!data.data?.length">
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                            Tidak ada data untuk filter yang dipilih.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <Pagination v-if="data.data?.length" :links="data.links" class="mt-4" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
