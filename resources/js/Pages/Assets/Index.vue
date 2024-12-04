<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { formatNumber } from '@/utils/numberFormat';
import { WrenchScrewdriverIcon } from '@heroicons/vue/24/solid';
import { statusOptions, getStatusClass } from '@/constants/assetStatus';

const props = defineProps({
    assets: Object,
    filters: Object,
    categories: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    branches: Array,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Aset', route: 'assets.index', active: true },
    { label: 'Kategori Aset', route: 'asset-categories.index', active: false },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Aset' },
    { key: 'category.name', label: 'Kategori' },
    { key: 'branch.branch_group.company.name', label: 'Perusahaan' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'purchase_date', label: 'Tanggal Pembelian' },
    { key: 'purchase_cost', label: 'Harga Pembelian' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const customFilters = computed(() => [
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal',
        label: 'Dari Tanggal'
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal',
        label: 'Sampai Tanggal'
    },
    {
        name: 'category_id',
        type: 'select',
        options: props.categories.map(category => ({ value: category.id, label: category.name })),
        multiple: true,
        placeholder: 'Pilih Kategori',
        label: 'Kategori'
    },
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map(company => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih Perusahaan',
        label: 'Perusahaan'
    },
    {
        name: 'branch_id',
        type: 'select',
        options: props.branches.map(branch => ({ value: branch.id, label: branch.name })),
        multiple: true,
        placeholder: 'Pilih Cabang',
        label: 'Cabang'
    },
    {
        name: 'status',
        type: 'select',
        options: statusOptions,
        multiple: true,
        placeholder: 'Pilih Status',
        label: 'Status'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    purchase_date: (value) => new Date(value).toLocaleDateString(),
    purchase_cost: (value) => formatNumber(value),
    status: (value) => `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(value)}">${statusOptions.find(option => option.value === value)?.label || value}</span>`
};

const sortableColumns = ['name', 'purchase_date', 'purchase_cost'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteAsset(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('assets.destroy', id), {
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

    router.delete(route('assets.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
            ids: ids,
        },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('assets.index'), {
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
    router.get(route('assets.index'), {
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
    <Head title="Assets" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Assets</h2>
        </template>

        <TabLinks :tabs="tabs" />

        <div class="min-w-min md:min-w-max mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="assets"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'assets.create' }"
                        :editRoute="{ name: 'assets.edit' }"
                        :deleteRoute="{ name: 'assets.destroy' }"
                        :viewRoute="{ name: 'assets.show' }"
                        :indexRoute="{ name: 'assets.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="assets"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="assets.index"
                        @delete="deleteAsset"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('asset-maintenance.index', item.id)" class="mr-3" target="_blank" title="Maintenance">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center align-middle w-4 h-4 md:ml-3 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50"
                                >
                                    <WrenchScrewdriverIcon class="h-4 w-4" />
                                </button>
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>