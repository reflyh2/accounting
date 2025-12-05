<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const page = usePage();

const props = defineProps({
    assetDisposals: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    statusOptions: Object,
    disposalTypeOptions: Object,
});

const currentSort = ref({ key: props.sort || 'disposal_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'disposal_date', label: 'Tgl Pelepasan' },
    { key: 'number', label: 'Nomor' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'disposal_type', label: 'Jenis' },
    { key: 'asset_disposal_details', label: 'Aset' },
    { key: 'proceeds_amount', label: 'Hasil' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

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
        options: branchOptions.value,
        multiple: true,
        placeholder: 'Pilih Cabang',
        label: 'Cabang'
    },
    {
        name: 'disposal_type',
        type: 'select',
        options: Object.entries(props.disposalTypeOptions).map(([value, label]) => ({ value, label })),
        multiple: true,
        placeholder: 'Pilih Jenis',
        label: 'Jenis Pelepasan'
    }
]);

const downloadOptions = [
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    disposal_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    proceeds_amount: (value) => `${formatNumber(value)}`,
};

const columnRenderers = {
    asset_disposal_details: (value) => value.map(detail => 
        `<a href="/assets/${detail.asset.id}" class="text-main-600 hover:text-main-800 hover:underline">${detail.asset.name}</a>`
    ).join(', '),    
    status: (value) => {
        const statusColors = {
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800',
            cancelled: 'bg-yellow-100 text-yellow-800',
            draft: 'bg-gray-100 text-gray-800'
        };
        const color = statusColors[value] || statusColors.default;
        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${color}">${props.statusOptions[value] || value}</span>`;
    },
    disposal_type: (value) => {
        return `<span>${props.disposalTypeOptions[value] || value}</span>`;
    },
};

const sortableColumns = ['disposal_date', 'number', 'branch.name', 'status'];
const defaultSort = { key: 'disposal_date', order: 'desc' };

function deleteItem(id) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-disposals.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleBulkDelete(ids) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-disposals.bulk-delete'), {
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
    router.get(route('asset-disposals.index'), {
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
    if (newFilters.page) {
        delete newFilters.page;
    }
    router.get(route('asset-disposals.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Pelepasan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Dokumen Pelepasan Aset</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="assetDisposals"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-disposals.create' }"
                        :editRoute="{ name: 'asset-disposals.edit' }"
                        :deleteRoute="{ name: 'asset-disposals.destroy' }"
                        :viewRoute="{ name: 'asset-disposals.show' }"
                        :indexRoute="{ name: 'asset-disposals.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-disposals"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-disposals.index"
                        itemKey="id"
                        searchPlaceholder="Cari no. dokumen, cabang..."
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                             <a :href="route('asset-disposals.print', item.id)" target="_blank" class="ml-2">
                                <AppPrintButton title="Cetak Dokumen Ini" />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 