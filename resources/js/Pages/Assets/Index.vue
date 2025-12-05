<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import { PrinterIcon } from '@heroicons/vue/24/solid';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const props = defineProps({
    assets: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    categories: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'acquisition_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama Aset' },
    { key: 'category.name', label: 'Kategori' },
    { key: 'type', label: 'Jenis' },
    { key: 'acquisition_date', label: 'Tanggal Perolehan' },
    { key: 'cost_basis', label: 'Nilai Perolehan' },
    { key: 'net_book_value', label: 'Nilai Buku' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

const categoryOptions = computed(() => 
    props.categories.map(category => ({ value: category.id, label: category.name }))
);

const assetTypeOptions = computed(() => [
    { value: 'tangible', label: 'Berwujud' },
    { value: 'intangible', label: 'Tidak Berwujud' }
]);

const statusOptions = computed(() => [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Tidak Aktif' },
    { value: 'disposed', label: 'Dilepas' },
    { value: 'sold', label: 'Dijual' },
    { value: 'scrapped', label: 'Dibuang' },
    { value: 'written_off', label: 'Dihapusbukukan' }
]);

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
        name: 'asset_category_id',
        type: 'select',
        options: categoryOptions.value,
        multiple: true,
        placeholder: 'Pilih kategori',
        label: 'Kategori'
    },
    {
        name: 'type',
        type: 'select',
        options: assetTypeOptions.value,
        multiple: true,
        placeholder: 'Pilih jenis',
        label: 'Jenis'
    },
    {
        name: 'status',
        type: 'select',
        options: statusOptions.value,
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
    acquisition_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    cost_basis: (value) => formatNumber(value),
    net_book_value: (value) => formatNumber(value),
    type: (value) => value === 'tangible' ? 'Berwujud' : 'Tidak Berwujud',
    status: (value) => {
        const statusMap = {
            'active': 'Aktif',
            'inactive': 'Tidak Aktif',
            'disposed': 'Dilepas',
            'sold': 'Dijual',
            'scrapped': 'Dibuang',
            'written_off': 'Dihapusbukukan'
        };
        return statusMap[value] || value;
    }
};

const sortableColumns = ['code', 'name', 'category.name', 'type', 'acquisition_date', 'cost_basis', 'net_book_value', 'status'];
const defaultSort = { key: 'acquisition_date', order: 'desc' };

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
    <Head title="Daftar Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Aset</h2>
        </template>

        <div class="mx-auto">
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
                            <a :href="route('assets.print', item.id)" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 