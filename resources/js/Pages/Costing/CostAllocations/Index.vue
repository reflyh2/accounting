<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    allocations: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    costPoolOptions: Array,
    allocationRuleOptions: Object,
});

const tableHeaders = [
    { key: 'cost_pool.name', label: 'Pool' },
    { key: 'sales_invoice_line.sales_invoice.invoice_number', label: 'No. Faktur' },
    { key: 'sales_invoice_line.sales_invoice.partner.name', label: 'Pelanggan' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'allocation_rule', label: 'Aturan' },
    { key: 'allocation_ratio', label: 'Rasio' },
    { key: 'period', label: 'Periode' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    amount: (value) => formatNumber(value),
    allocation_ratio: (value) => value ? (value * 100).toFixed(2) + '%' : '-',
    allocation_rule: (value) => props.allocationRuleOptions?.[value] || value,
};

const currentSort = ref({ key: props.sort || 'created_at', order: props.order || 'desc' });
const sortableColumns = ['amount', 'period', 'allocation_ratio'];
const defaultSort = { key: 'created_at', order: 'desc' };

const currentFilters = ref(props.filters || {});

const allocationRuleFilterOptions = computed(() =>
    Object.entries(props.allocationRuleOptions || {}).map(([value, label]) => ({
        value,
        label,
    }))
);

const customFilters = computed(() => [
    {
        name: 'cost_pool_id',
        type: 'select',
        options: props.costPoolOptions.map((pool) => ({ value: pool.id, label: pool.label })),
        multiple: true,
        placeholder: 'Pilih Pool',
        label: 'Pool Biaya',
    },
    {
        name: 'allocation_rule',
        type: 'select',
        options: allocationRuleFilterOptions.value,
        multiple: true,
        placeholder: 'Pilih Aturan',
        label: 'Aturan Alokasi',
    },
    {
        name: 'period',
        type: 'text',
        placeholder: '2026-01',
        label: 'Periode',
    },
]);

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('costing.cost-allocations.index'), {
        ...route().params,
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('costing.cost-allocations.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Alokasi Biaya" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>Alokasi Biaya</h2>
                <Link :href="route('costing.cost-allocations.batch')">
                    <AppPrimaryButton>Jalankan Alokasi Batch</AppPrimaryButton>
                </Link>
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="allocations"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :viewRoute="{ name: 'costing.cost-allocations.show' }"
                        :indexRoute="{ name: 'costing.cost-allocations.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="costing.cost-allocations.index"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
