<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    finishedGoodsReceipts: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    workOrders: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'receipt_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'receipt_date', label: 'Tanggal' },
    { key: 'receipt_number', label: 'Nomor Receipt' },
    { key: 'work_order.wo_number', label: 'Work Order' },
    { key: 'status', label: 'Status' },
    { key: 'quantity_good', label: 'Qty Good' },
    { key: 'unit_cost', label: 'Unit Cost' },
    { key: 'total_cost', label: 'Total Cost' },
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
        name: 'status',
        type: 'select',
        options: [
            { value: 'draft', label: 'Draft' },
            { value: 'posted', label: 'Posted' }
        ],
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
    receipt_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    status: (value) => value === 'draft' ? 'Draft' : 'Posted',
    quantity_good: (value) => formatNumber(value),
    unit_cost: (value) => formatNumber(value),
    total_cost: (value) => formatNumber(value),
};

const sortableColumns = ['receipt_date', 'receipt_number', 'status'];
const defaultSort = { key: 'receipt_date', order: 'desc' };

function deleteReceipt(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('finished-goods-receipts.destroy', id), {
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

    router.delete(route('finished-goods-receipts.bulk-delete'), {
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
    router.get(route('finished-goods-receipts.index'), {
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
    router.get(route('finished-goods-receipts.index'), {
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
    <Head title="Finished Goods Receipts" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Finished Goods Receipts</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="finishedGoodsReceipts"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'finished-goods-receipts.create' }"
                        :editRoute="{ name: 'finished-goods-receipts.edit' }"
                        :deleteRoute="{ name: 'finished-goods-receipts.destroy' }"
                        :viewRoute="{ name: 'finished-goods-receipts.show' }"
                        :indexRoute="{ name: 'finished-goods-receipts.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="finished-goods-receipts"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="finished-goods-receipts.index"
                        @delete="deleteReceipt"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
