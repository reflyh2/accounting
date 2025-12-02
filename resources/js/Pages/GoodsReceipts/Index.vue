<script setup>
import { ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    goodsReceipts: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    companies: Array,
    branches: Array,
    suppliers: Array,
    statusOptions: Object,
    perPage: [Number, String],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'receipt_date', order: props.order || 'desc' });
const currentFilters = ref({ ...props.filters });

const tableHeaders = [
    { key: 'receipt_date', label: 'Tanggal' },
    { key: 'receipt_number', label: 'Nomor' },
    { key: 'purchase_order.order_number', label: 'PO' },
    { key: 'purchase_order.partner.name', label: 'Supplier' },
    { key: 'location.name', label: 'Lokasi' },
    { key: 'status', label: 'Status' },
    { key: 'total_value', label: 'Nilai' },
    { key: 'actions', label: '' },
];

const sortableColumns = ['receipt_date', 'receipt_number', 'total_value'];
const defaultSort = { key: 'receipt_date', order: 'desc' };

const columnFormatters = {
    receipt_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    total_value: (value) => (value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
    status: (value) => props.statusOptions?.[value] || value,
};

const customFilters = computed(() => [
    {
        name: 'search',
        type: 'text',
        label: 'Pencarian',
        placeholder: 'Nomor GRN atau PO',
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
        name: 'status',
        type: 'select',
        label: 'Status',
        multiple: true,
        options: Object.entries(props.statusOptions || {}).map(([value, label]) => ({ value, label })),
    },
]);

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('goods-receipts.index'), {
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
    router.get(route('goods-receipts.index'), {
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
    <Head title="Penerimaan Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Penerimaan Pembelian</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="goodsReceipts"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'goods-receipts.create' }"
                        :viewRoute="{ name: 'goods-receipts.show' }"
                        :indexRoute="{ name: 'goods-receipts.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        :enableBulkActions="false"
                        routeName="goods-receipts.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor GRN atau PO..."
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

