<script setup>
import { ref, computed } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    purchaseReturns: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    companies: Array,
    branches: Array,
    suppliers: Array,
    reasonOptions: Array,
    statusOptions: Object,
    perPage: [Number, String],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'return_date', order: props.order || 'desc' });
const currentFilters = ref({ ...props.filters });

const tableHeaders = [
    { key: 'return_date', label: 'Tanggal' },
    { key: 'return_number', label: 'Nomor Retur' },
    { key: 'purchase_order.order_number', label: 'PO' },
    { key: 'goods_receipt.receipt_number', label: 'GRN' },
    { key: 'partner.name', label: 'Supplier' },
    { key: 'reason_label', label: 'Alasan' },
    { key: 'total_value', label: 'Nilai' },
    { key: 'actions', label: '' },
];

const sortableColumns = ['return_date', 'return_number', 'total_value'];
const defaultSort = { key: 'return_date', order: 'desc' };

const columnFormatters = {
    return_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    total_value: (value) => (value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    reason_label: (value) => value || '-',
};

const customFilters = computed(() => [
    {
        name: 'search',
        type: 'text',
        label: 'Pencarian',
        placeholder: 'Nomor retur / PO / GRN',
    },
    {
        name: 'from_date',
        type: 'date',
        label: 'Dari Tanggal',
    },
    {
        name: 'to_date',
        type: 'date',
        label: 'Sampai Tanggal',
    },
    {
        name: 'company_id',
        type: 'select',
        label: 'Perusahaan',
        multiple: true,
        options: props.companies.map(company => ({ value: company.id, label: company.name })),
    },
    {
        name: 'branch_id',
        type: 'select',
        label: 'Cabang',
        multiple: true,
        options: props.branches.map(branch => ({
            value: branch.id,
            label: branch.company ? `${branch.name} (${branch.company})` : branch.name,
        })),
    },
    {
        name: 'partner_id',
        type: 'select',
        label: 'Supplier',
        multiple: true,
        options: props.suppliers.map(supplier => ({ value: supplier.id, label: supplier.name })),
    },
    {
        name: 'reason_code',
        type: 'select',
        label: 'Alasan',
        multiple: true,
        options: props.reasonOptions,
    },
    {
        name: 'status',
        type: 'select',
        label: 'Status',
        multiple: true,
        options: Object.entries(props.statusOptions || {}).map(([value, label]) => ({ value, label })),
    },
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' },
];

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('purchase-returns.index'), {
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
    router.get(route('purchase-returns.index'), {
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

function deletePurchaseReturn(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('purchase-returns.destroy', id), {
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

    router.delete(route('purchase-returns.bulk-delete'), {
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
    <Head title="Retur Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Retur Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="purchaseReturns"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'purchase-returns.create' }"
                        :editRoute="null"
                        :deleteRoute="{ name: 'purchase-returns.destroy' }"
                        :viewRoute="{ name: 'purchase-returns.show' }"
                        :indexRoute="{ name: 'purchase-returns.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        :downloadOptions="downloadOptions"
                        :enableBulkActions="true"
                        downloadBaseRoute="purchase-returns"
                        routeName="purchase-returns.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor retur, PO, atau GRN..."
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deletePurchaseReturn"
                        @bulkDelete="handleBulkDelete"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
