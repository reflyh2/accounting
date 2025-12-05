<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';

const page = usePage();

const props = defineProps({
    assetTransfers: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    statusOptions: Object,
});

const currentSort = ref({ key: props.sort || 'transfer_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'transfer_date', label: 'Tgl Transfer' },
    { key: 'number', label: 'Nomor' },
    { key: 'from_branch.name', label: 'Dari Cabang' },
    { key: 'to_branch.name', label: 'Ke Cabang' },
    { key: 'asset_transfer_details', label: 'Aset' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: `${branch.name} (${branch.branch_group?.company?.name})` }))
);

const statusFilterOptions = computed(() =>
    Object.entries(props.statusOptions).map(([value, label]) => ({ value, label }))
);

const customFilters = computed(() => [
    { name: 'from_date', type: 'date', placeholder: 'Dari Tanggal', label: 'Dari Tanggal' },
    { name: 'to_date', type: 'date', placeholder: 'Sampai Tanggal', label: 'Sampai Tanggal' },
    { name: 'from_company_id', type: 'select', options: props.companies.map(c => ({value: c.id, label: c.name})), multiple: true, placeholder: 'Dari Perusahaan', label: 'Dari Perusahaan' },
    { name: 'from_branch_id', type: 'select', options: branchOptions.value, multiple: true, placeholder: 'Dari Cabang', label: 'Dari Cabang' },
    { name: 'to_company_id', type: 'select', options: props.companies.map(c => ({value: c.id, label: c.name})), multiple: true, placeholder: 'Ke Perusahaan', label: 'Ke Perusahaan' },
    { name: 'to_branch_id', type: 'select', options: branchOptions.value, multiple: true, placeholder: 'Ke Cabang', label: 'Ke Cabang' },
    { name: 'status', type: 'select', options: statusFilterOptions.value, multiple: true, placeholder: 'Pilih Status', label: 'Status' }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    transfer_date: (value) => new Date(value).toLocaleDateString('id-ID'),
};

const columnRenderers = {
    asset_transfer_details: (value) => value.map(detail => 
        `<a target="_blank" href="/assets/${detail.asset.id}" class="text-main-600 hover:text-main-800 hover:underline">${detail.asset.name}</a>`
    ).join(', '),    
    status: (value) => {
        const statusColors = {
            draft: 'bg-yellow-100 text-yellow-800',
            approved: 'bg-green-100 text-green-800',
            rejected: 'bg-red-100 text-red-800',
            cancelled: 'bg-gray-100 text-gray-800',
            default: 'bg-gray-100 text-gray-800'
        };
        const color = statusColors[value] || statusColors.default;
        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${color}">${props.statusOptions[value] || value}</span>`;
    },
};

const sortableColumns = ['transfer_date', 'number', 'from_branch.name', 'to_branch.name', 'status'];
const defaultSort = { key: 'transfer_date', order: 'desc' };

function deleteItem(id) {
    router.delete(route('asset-transfers.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function handleBulkDelete(ids) {
    router.delete(route('asset-transfers.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { ids: ids },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('asset-transfers.index'), {
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
    router.get(route('asset-transfers.index'), {
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
    <Head title="Transfer Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Transfer Aset</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="assetTransfers"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-transfers.create' }"
                        :editRoute="{ name: 'asset-transfers.edit' }"
                        :deleteRoute="{ name: 'asset-transfers.destroy' }"
                        :viewRoute="{ name: 'asset-transfers.show' }"
                        :indexRoute="{ name: 'asset-transfers.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-transfers"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-transfers.index"
                        itemKey="id"
                        searchPlaceholder="Cari no. transfer, cabang..."
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 