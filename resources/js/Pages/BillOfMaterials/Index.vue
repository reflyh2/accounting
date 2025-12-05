<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    boms: Object,
    filters: Object,
    companies: Array,
    finishedProducts: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'created_at', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'bom_number', label: 'Nomor BOM' },
    { key: 'name', label: 'Nama BOM' },
    { key: 'finished_product.name', label: 'Produk Jadi' },
    { key: 'finished_quantity', label: 'Kuantitas' },
    { key: 'finished_uom.name', label: 'Satuan' },
    { key: 'version', label: 'Versi' },
    { key: 'status', label: 'Status' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'bom_lines_count', label: 'Komponen' },
    { key: 'actions', label: '' }
];

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' }
];

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map(company => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan'
    },
    {
        name: 'status',
        type: 'select',
        options: statusOptions,
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    },
    {
        name: 'finished_product_id',
        type: 'select',
        options: props.finishedProducts.map(product => ({ value: product.id, label: product.name })),
        placeholder: 'Pilih produk jadi',
        label: 'Produk Jadi'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    finished_quantity: (value) => formatNumber(value),
    bom_lines_count: (value) => formatNumber(value),
    status: (value) => {
        const statusLabels = {
            'draft': 'Draft',
            'active': 'Aktif',
            'inactive': 'Tidak Aktif'
        };
        return statusLabels[value] || value;
    }
};

const sortableColumns = ['bom_number', 'name', 'finished_product.name', 'version', 'status', 'created_at', 'bom_lines_count'];
const defaultSort = { key: 'created_at', order: 'desc' };

function deleteBom(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('bill-of-materials.destroy', id), {
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

    router.delete(route('bill-of-materials.bulk-delete'), {
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
    router.get(route('bill-of-materials.index'), {
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
    router.get(route('bill-of-materials.index'), {
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
    <Head title="Bill of Materials" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Bill of Materials (BOM)</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="boms"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'bill-of-materials.create' }"
                        :editRoute="{ name: 'bill-of-materials.edit' }"
                        :deleteRoute="{ name: 'bill-of-materials.destroy' }"
                        :viewRoute="{ name: 'bill-of-materials.show' }"
                        :indexRoute="{ name: 'bill-of-materials.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="bill-of-materials"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="bill-of-materials.index"
                        @delete="deleteBom"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
