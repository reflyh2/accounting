<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const props = defineProps({
    componentScraps: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    workOrders: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'scrap_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'scrap_date', label: 'Tanggal Scrap' },
    { key: 'work_order.wo_number', label: 'Work Order' },
    { key: 'component_product.name', label: 'Komponen' },
    { key: 'component_product_variant.name', label: 'Varian' },
    { key: 'scrap_quantity', label: 'Quantity Scrap' },
    { key: 'uom.name', label: 'Satuan' },
    { key: 'scrap_reason', label: 'Alasan Scrap' },
    { key: 'is_backflush', label: 'Backflush' },
    { key: 'branch.branch_group.company.name', label: 'Perusahaan' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

const workOrderOptions = computed(() => 
    props.workOrders.map(wo => ({ value: wo.id, label: wo.wo_number }))
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
        name: 'work_order_id',
        type: 'select',
        options: workOrderOptions.value,
        multiple: true,
        placeholder: 'Pilih Work Order',
        label: 'Work Order'
    },
    {
        name: 'is_backflush',
        type: 'select',
        options: [
            { value: 'true', label: 'Ya' },
            { value: 'false', label: 'Tidak' }
        ],
        placeholder: 'Pilih Backflush',
        label: 'Backflush'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    scrap_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    scrap_quantity: (value) => formatNumber(value),
    is_backflush: (value) => value ? 'Ya' : 'Tidak',
};

const sortableColumns = ['scrap_date', 'scrap_quantity', 'scrap_reason'];
const defaultSort = { key: 'scrap_date', order: 'desc' };

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('component-scraps.index'), {
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
    router.get(route('component-scraps.index'), {
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
    <Head title="Component Scraps" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Component Scraps</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="componentScraps"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :viewRoute="{ name: 'component-scraps.show' }"
                        :indexRoute="{ name: 'component-scraps.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="component-scraps"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="component-scraps.index"
                        :showCreateButton="false"
                        :showEditButton="false"
                        :showDeleteButton="false"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

