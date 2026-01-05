<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { renderStatusPillHtml } from '@/utils/statusPillHtml';

const props = defineProps({
    purchaseOrders: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    branches: Array,
    suppliers: Array,
    statusOptions: Object,
});

const tableHeaders = [
    { key: 'order_date', label: 'Tgl PO' },
    { key: 'order_number', label: 'Nomor' },
    { key: 'partner.name', label: 'Supplier' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'total_amount', label: 'Total' },
    { key: 'status', label: 'Status' },
    { key: 'expected_date', label: 'Tgl Kedatangan' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    order_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    expected_date: (value) => (value ? new Date(value).toLocaleDateString('id-ID') : '-'),
    total_amount: (value) => formatNumber(value),
    status: (value) => props.statusOptions?.[value] || value,
};

const columnRenderers = {
    status: (value) => renderStatusPillHtml(DocumentStatusKind.PURCHASE_ORDER, value, 'sm'),
};

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const currentSort = ref({ key: props.sort || 'order_date', order: props.order || 'desc' });
const sortableColumns = ['order_date', 'order_number', 'status', 'total_amount', 'expected_date'];
const defaultSort = { key: 'order_date', order: 'desc' };

const currentFilters = ref(props.filters || {});

const branchOptions = computed(() => props.branches.map((branch) => ({
    value: branch.id,
    label: branch.name,
    company_id: branch.company_id,
})));

const supplierOptions = computed(() => props.suppliers.map((supplier) => ({
    value: supplier.id,
    label: `${supplier.code} — ${supplier.name}`,
})));

const statusFilterOptions = computed(() =>
    Object.entries(props.statusOptions || {}).map(([value, label]) => ({
        value,
        label,
    }))
);

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map((company) => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih Perusahaan',
        label: 'Perusahaan',
    },
    {
        name: 'branch_id',
        type: 'select',
        options: branchOptions.value,
        multiple: true,
        placeholder: 'Pilih Cabang',
        label: 'Cabang',
    },
    {
        name: 'partner_id',
        type: 'select',
        options: supplierOptions.value,
        multiple: true,
        placeholder: 'Pilih Supplier',
        label: 'Supplier',
    },
    {
        name: 'status',
        type: 'select',
        options: statusFilterOptions.value,
        multiple: true,
        placeholder: 'Status',
        label: 'Status',
    },
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal',
        label: 'Dari Tanggal',
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal',
        label: 'Sampai Tanggal',
    },
]);

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('purchase-orders.index'), {
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
    router.get(route('purchase-orders.index'), {
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

function handleDelete(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('purchase-orders.destroy', id), {
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

    router.delete(route('purchase-orders.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
            ids: ids,
        },
    });
}

</script>

<template>
    <Head title="Purchase Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Purchase Order</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="purchaseOrders"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'purchase-orders.create' }"
                        :editRoute="{ name: 'purchase-orders.edit' }"
                        :viewRoute="{ name: 'purchase-orders.show' }"
                        :deleteRoute="{ name: 'purchase-orders.destroy' }"
                        :indexRoute="{ name: 'purchase-orders.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="purchase-orders.index"
                        :enableBulkActions="true"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="purchase-orders"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="handleDelete"
                        @bulkDelete="handleBulkDelete"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

