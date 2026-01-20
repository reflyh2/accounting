<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const props = defineProps({
    maintenances: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    assets: Array,
    maintenanceTypes: Object,
    statusOptions: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'maintenance_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'asset.name', label: 'Aset' },
    { key: 'maintenance_date', label: 'Tanggal' },
    { key: 'maintenance_type', label: 'Jenis' },
    { key: 'total_cost', label: 'Total Biaya' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

const assetOptions = computed(() => 
    props.assets.map(asset => ({ value: asset.id, label: `${asset.code} - ${asset.name}` }))
);

const maintenanceTypeOptions = computed(() => 
    Object.entries(props.maintenanceTypes).map(([value, label]) => ({ value, label }))
);

const statusSelectOptions = computed(() => 
    Object.entries(props.statusOptions).map(([value, label]) => ({ value, label }))
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
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan'
    },
    {
        name: 'branch_id',
        type: 'select',
        options: branchOptions.value,
        multiple: true,
        placeholder: 'Pilih cabang',
        label: 'Cabang'
    },
    {
        name: 'asset_id',
        type: 'select',
        options: assetOptions.value,
        multiple: true,
        placeholder: 'Pilih aset',
        label: 'Aset'
    },
    {
        name: 'maintenance_type',
        type: 'select',
        options: maintenanceTypeOptions.value,
        multiple: true,
        placeholder: 'Pilih jenis',
        label: 'Jenis Pemeliharaan'
    },
    {
        name: 'status',
        type: 'select',
        options: statusSelectOptions.value,
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    maintenance_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    total_cost: (value) => formatNumber(value),
    maintenance_type: (value) => props.maintenanceTypes[value] || value,
    status: (value) => props.statusOptions[value] || value,
};

function getStatusPillClass(status) {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'draft':
            return 'bg-amber-100 text-amber-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

const columnRenderers = {
    status: (value) => {
        const label = props.statusOptions[value] || value;
        const pillClass = getStatusPillClass(value);
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${pillClass}">${label}</span>`;
    },
};

const sortableColumns = ['code', 'asset.name', 'maintenance_date', 'maintenance_type', 'total_cost', 'status'];
const defaultSort = { key: 'maintenance_date', order: 'desc' };

function deleteMaintenance(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-maintenances.destroy', id), {
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

    router.delete(route('asset-maintenances.bulk-delete'), {
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
    router.get(route('asset-maintenances.index'), {
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
    router.get(route('asset-maintenances.index'), {
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
    <Head title="Pemeliharaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pemeliharaan Aset</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="maintenances"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-maintenances.create' }"
                        :editRoute="{ name: 'asset-maintenances.edit' }"
                        :deleteRoute="{ name: 'asset-maintenances.destroy' }"
                        :viewRoute="{ name: 'asset-maintenances.show' }"
                        :indexRoute="{ name: 'asset-maintenances.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-maintenances"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-maintenances.index"
                        :enableBulkActions="true"
                        @delete="deleteMaintenance"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('asset-maintenances.print', item.id)" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
