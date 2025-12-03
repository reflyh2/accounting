<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    salesOrders: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    branches: Array,
    customers: Array,
    statusOptions: Object,
});

const tableHeaders = [
    { key: 'order_date', label: 'Tgl Order' },
    { key: 'order_number', label: 'Nomor' },
    { key: 'partner.name', label: 'Pelanggan' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'total_amount', label: 'Total' },
    { key: 'reserve_stock', label: 'Reservasi' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    order_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    total_amount: (value) => formatNumber(value),
    reserve_stock: (value) => (value ? 'Ya' : 'Tidak'),
};

const currentSort = ref({ key: props.sort || 'order_date', order: props.order || 'desc' });
const sortableColumns = ['order_date', 'order_number', 'status', 'total_amount'];
const defaultSort = { key: 'order_date', order: 'desc' };
const currentFilters = ref(props.filters || {});

const branchOptions = computed(() =>
    props.branches.map((branch) => ({
        value: branch.id,
        label: branch.name,
        company_id: branch.company_id,
    }))
);

const customerOptions = computed(() =>
    props.customers.map((customer) => ({
        value: customer.id,
        label: `${customer.code} — ${customer.name}`,
        company_ids: customer.company_ids,
    }))
);

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
        options: customerOptions.value,
        multiple: true,
        placeholder: 'Pilih Pelanggan',
        label: 'Pelanggan',
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
        name: 'reserve_stock',
        type: 'select',
        options: [
            { value: 1, label: 'Hanya Reservasi' },
            { value: 0, label: 'Tanpa Reservasi' },
        ],
        placeholder: 'Reservasi',
        label: 'Reservasi Stok',
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
    router.get(
        route('sales-orders.index'),
        {
            ...route().params,
            ...currentFilters.value,
            sort: newSort.key,
            order: newSort.order,
            per_page: props.perPage,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(
        route('sales-orders.index'),
        {
            ...currentFilters.value,
            sort: currentSort.value.key,
            order: currentSort.value.order,
            per_page: newFilters.per_page || props.perPage,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}
</script>

<template>
    <Head title="Sales Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Sales Order</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="salesOrders"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'sales-orders.create' }"
                        :editRoute="{ name: 'sales-orders.edit' }"
                        :viewRoute="{ name: 'sales-orders.show' }"
                        :deleteRoute="{ name: 'sales-orders.destroy' }"
                        :indexRoute="{ name: 'sales-orders.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="sales-orders.index"
                        :enableBulkActions="false"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #status="{ item }">
                            <DocumentStatusPill
                                :documentKind="DocumentStatusKind.SALES_ORDER"
                                :status="item.status"
                                size="sm"
                            />
                        </template>
                        <template #reserve_stock="{ item }">
                            <span
                                :class="item.reserve_stock ? 'text-emerald-600 font-medium' : 'text-gray-500'"
                            >
                                {{ item.reserve_stock ? 'Ya' : 'Tidak' }}
                            </span>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

