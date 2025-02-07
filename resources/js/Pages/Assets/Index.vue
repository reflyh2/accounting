<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    assets: Object,
    categories: Array,
    companies: Array,
    branches: Array,
    assetTypes: Array,
    acquisitionTypes: Array,
    statuses: Array,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

// Add computed property for filtered branches
const filteredBranches = computed(() => {
    const selectedCompanies = currentFilters.value.company_id || [];
    if (selectedCompanies.length === 0) {
        return props.branches;
    }
    return props.branches.filter(branch => 
        selectedCompanies.includes(branch.branch_group?.company_id)
    );
});

const tabs = [
    { label: 'Aset', route: 'assets.index', active: true },
    { label: 'Kategori Aset', route: 'asset-categories.index', active: false },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Aset' },
    { key: 'category.name', label: 'Kategori' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'asset_type', label: 'Jenis Aset' },
    { key: 'acquisition_type', label: 'Jenis Perolehan' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map(company => ({
            value: company.id,
            label: company.name
        })),
        multiple: true,
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan',
        maxRows: 3,
    },
    {
        name: 'branch_id',
        type: 'select',
        options: filteredBranches.value.map(branch => ({
            value: branch.id,
            label: `${branch.name} (${branch.branch_group?.company?.name || 'Tanpa Perusahaan'})`
        })),
        multiple: true,
        placeholder: 'Pilih cabang',
        label: 'Cabang',
        maxRows: 3,
    },
    {
        name: 'category_id',
        type: 'select',
        options: props.categories.map(category => ({
            value: category.id,
            label: category.name
        })),
        multiple: true,
        placeholder: 'Pilih kategori',
        label: 'Kategori'
    },
    {
        name: 'asset_type',
        type: 'select',
        options: props.assetTypes,
        multiple: true,
        placeholder: 'Pilih jenis aset',
        label: 'Jenis Aset'
    },
    {
        name: 'acquisition_type',
        type: 'select',
        options: props.acquisitionTypes,
        multiple: true,
        placeholder: 'Pilih jenis perolehan',
        label: 'Jenis Perolehan'
    },
    {
        name: 'status',
        type: 'select',
        options: props.statuses,
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const downloadOptions = [
    { value: 'xlsx', label: 'Excel' },
    { value: 'csv', label: 'CSV' },
    { value: 'pdf', label: 'PDF' }
];

const sortableColumns = [
    'name', 'category.name', 'asset_type', 'acquisition_type', 
    'branch.name', 'current_value', 'status'
];

const defaultSort = { key: 'name', order: 'asc' };

const columnFormatters = {
    purchase_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    purchase_cost: (value) => formatNumber(value),
    status: (value) => formatValue(value, 'status'),
    asset_type: (value) => formatValue(value, 'asset_type'),
    acquisition_type: (value) => formatValue(value, 'acquisition_type'),
    status: (value) => ({
        'active': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>',
        'inactive': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Tidak Aktif</span>',
        'maintenance': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pemeliharaan</span>',
        'disposed': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Dilepas</span>'
    })[value] || value
};

function formatValue(value, type) {
    switch (type) {
        case 'status':
            return value === 'active' ? 'Aktif' :
                   value === 'inactive' ? 'Tidak Aktif' :
                   value === 'maintenance' ? 'Pemeliharaan' :
                   value === 'disposed' ? 'Dilepas' : value;
        case 'asset_type':
            return value === 'tangible' ? 'Berwujud' :
                   value === 'intangible' ? 'Tidak Berwujud' : value;
        case 'acquisition_type':
            return value === 'outright_purchase' ? 'Pembelian Langsung' :
                   value === 'financed_purchase' ? 'Pembelian Kredit' :
                   value === 'fixed_rental' ? 'Sewa Periode Tetap' :
                   value === 'periodic_rental' ? 'Sewa Berkala' :
                   value === 'casual_rental' ? 'Sewa Sekali Pakai' : value;
        default:
            return value;
    }
}

function getStatusClass(status) {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'inactive':
            return 'bg-gray-100 text-gray-800';
        case 'maintenance':
            return 'bg-yellow-100 text-yellow-800';
        case 'disposed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function deleteAsset(id) {
    router.delete(route('assets.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: window.location.search.substring(1)
        },
    });
}

function handleBulkDelete(ids) {
    router.delete(route('assets.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: window.location.search.substring(1),
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
    <Head title="Daftar Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Aset</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="assets"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :createRoute="{ name: 'assets.create' }"
                        :editRoute="{ name: 'assets.edit' }"
                        :deleteRoute="{ name: 'assets.destroy' }"
                        :viewRoute="{ name: 'assets.show' }"
                        :indexRoute="{ name: 'assets.index' }"
                        :customFilters="customFilters"
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
                        <template #status="{ item }">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="[
                                    'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                    getStatusClass(item.status)
                                ]">
                                    {{ formatValue(item.status, 'status') }}
                                </span>
                            </td>
                        </template>

                        <template #lease_status="{ item }">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div v-if="item.acquisition_type === 'lease'" class="flex space-x-2">
                                    <router-link
                                        :to="route('asset-leases.show', item.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        View Lease
                                    </router-link>
                                    <router-link
                                        :to="route('asset-lease-payments.index', item.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        Payments
                                    </router-link>
                                </div>
                                <span v-else>-</span>
                            </td>
                        </template>

                        <template #empty-message>
                            <div class="text-center py-6">
                                <p class="text-gray-500">Tidak ada data aset yang ditemukan</p>
                            </div>
                        </template>

                        <AppModal 
                            :show="confirmingAssetDeletion" 
                            @close="confirmingAssetDeletion = false"
                        >
                            <div class="p-6">
                                <h2 class="text-lg font-medium text-gray-900">
                                    Apakah Anda yakin ingin menghapus aset ini?
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    Tindakan ini tidak dapat dibatalkan.
                                </p>

                                <div class="mt-6 flex justify-end">
                                    <AppSecondaryButton @click="confirmingAssetDeletion = false" class="mr-3">
                                        Batal
                                    </AppSecondaryButton>

                                    <AppDangerButton @click="deleteAsset">
                                        Hapus Aset
                                    </AppDangerButton>
                                </div>
                            </div>
                        </AppModal>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>