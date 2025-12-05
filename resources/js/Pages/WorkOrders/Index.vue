<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    workOrders: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    boms: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'scheduled_start_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'wo_number', label: 'Nomor WO' },
    { key: 'bom.finished_product.name', label: 'Produk Jadi' },
    { key: 'quantity_planned', label: 'Qty Direncanakan' },
    { key: 'work_order_receipts_sum_quantity_received', label: 'Qty Diterima' },
    { key: 'progress_percentage', label: 'Progress' },
    { key: 'status', label: 'Status' },
    { key: 'scheduled_start_date', label: 'Tanggal Mulai' },
    { key: 'scheduled_end_date', label: 'Tanggal Selesai' },
    { key: 'branch.branch_group.company.name', label: 'Perusahaan' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() =>
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'released', label: 'Released' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
];

const bomOptions = computed(() =>
    props.boms.map(bom => ({ value: bom.id, label: `${bom.name} - ${bom.finished_product?.name}` }))
);

const customFilters = computed(() => [
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal',
        label: 'Dari Tanggal Mulai'
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal',
        label: 'Sampai Tanggal Mulai'
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
        name: 'status',
        type: 'select',
        options: statusOptions,
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    },
    {
        name: 'bom_id',
        type: 'select',
        options: bomOptions.value,
        placeholder: 'Pilih BOM',
        label: 'Bill of Material'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    quantity_planned: (value) => formatNumber(value),
    work_order_receipts_sum_quantity_received: (value) => formatNumber(value || 0),
    progress_percentage: (value) => `${formatNumber(value || 0)}%`,
    scheduled_start_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    scheduled_end_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    status: (value) => {
        const statusLabels = {
            'draft': 'Draft',
            'released': 'Released',
            'in_progress': 'In Progress',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };
        return statusLabels[value] || value;
    }
};

const sortableColumns = ['wo_number', 'bom.finished_product.name', 'quantity_planned', 'status', 'scheduled_start_date', 'scheduled_end_date'];
const defaultSort = { key: 'scheduled_start_date', order: 'desc' };

function deleteWorkOrder(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('work-orders.destroy', id), {
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

    router.delete(route('work-orders.bulk-delete'), {
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
    router.get(route('work-orders.index'), {
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
    router.get(route('work-orders.index'), {
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
    <Head title="Work Orders" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Work Orders (WO)</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="workOrders"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'work-orders.create' }"
                        :editRoute="{ name: 'work-orders.edit' }"
                        :deleteRoute="{ name: 'work-orders.destroy' }"
                        :viewRoute="{ name: 'work-orders.show' }"
                        :indexRoute="{ name: 'work-orders.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="work-orders"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="work-orders.index"
                        @delete="deleteWorkOrder"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
