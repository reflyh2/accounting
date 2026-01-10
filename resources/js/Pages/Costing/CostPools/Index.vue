<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    costPools: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    poolTypeOptions: Object,
    activeOptions: Array,
});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama Pool' },
    { key: 'pool_type', label: 'Tipe' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'accumulated_amount', label: 'Akumulasi' },
    { key: 'allocated_amount', label: 'Teralokasi' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    accumulated_amount: (value) => formatNumber(value),
    allocated_amount: (value) => formatNumber(value),
    pool_type: (value) => props.poolTypeOptions?.[value] || value,
    is_active: (value) => value ? 'Aktif' : 'Non-aktif',
};

const currentSort = ref({ key: props.sort || 'created_at', order: props.order || 'desc' });
const sortableColumns = ['code', 'name', 'accumulated_amount', 'allocated_amount'];
const defaultSort = { key: 'created_at', order: 'desc' };

const currentFilters = ref(props.filters || {});

const poolTypeFilterOptions = computed(() =>
    Object.entries(props.poolTypeOptions || {}).map(([value, label]) => ({
        value,
        label,
    }))
);

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map((company) => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih Perusahaan',
        label: 'Perusahaan',
    },
    {
        name: 'pool_type',
        type: 'select',
        options: poolTypeFilterOptions.value,
        multiple: true,
        placeholder: 'Pilih Tipe',
        label: 'Tipe Pool',
    },
    {
        name: 'is_active',
        type: 'select',
        options: props.activeOptions,
        multiple: false,
        placeholder: 'Status',
        label: 'Status',
    },
]);

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('costing.cost-pools.index'), {
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
    router.get(route('costing.cost-pools.index'), {
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

function deleteCostPool(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('costing.cost-pools.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleBulkDelete(ids) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('costing.cost-pools.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
            ids: ids,
        },
    });
}
</script>

<template>
    <Head title="Pool Biaya" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pool Biaya</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="costPools"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'costing.cost-pools.create' }"
                        :editRoute="{ name: 'costing.cost-pools.edit' }"
                        :viewRoute="{ name: 'costing.cost-pools.show' }"
                        :deleteRoute="{ name: 'costing.cost-pools.destroy' }"
                        :indexRoute="{ name: 'costing.cost-pools.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="costing.cost-pools.index"
                        :enableBulkActions="true"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deleteCostPool"
                        @bulkDelete="handleBulkDelete"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
