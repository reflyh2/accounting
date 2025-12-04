<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    salesReturns: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    customers: Array,
    reasonOptions: Array,
    statusOptions: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'return_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'return_date', label: 'Tanggal' },
    { key: 'return_number', label: 'Nomor Retur' },
    { key: 'sales_order.order_number', label: 'Nomor SO' },
    { key: 'branch.branch_group.company.name', label: 'Perusahaan' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'partner.name', label: 'Customer' },
    { key: 'reason_label', label: 'Alasan' },
    { key: 'total_value_base', label: 'Total' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() =>
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

const customerOptions = computed(() =>
    props.customers.map(customer => ({ value: customer.id, label: customer.name }))
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
        name: 'partner_id',
        type: 'select',
        options: customerOptions.value,
        multiple: true,
        placeholder: 'Pilih customer',
        label: 'Customer'
    },
    {
        name: 'reason_code',
        type: 'select',
        options: props.reasonOptions,
        multiple: true,
        placeholder: 'Pilih alasan',
        label: 'Alasan'
    },
    {
        name: 'status',
        type: 'select',
        options: Object.entries(props.statusOptions).map(([value, label]) => ({ value, label })),
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
    return_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    total_value_base: (value) => formatNumber(value),
};

const sortableColumns = ['return_date', 'return_number', 'sales_order.order_number', 'branches.name', 'reason_label', 'total_value_base'];
const defaultSort = { key: 'return_date', order: 'desc' };

function deleteSalesReturn(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('sales-returns.destroy', id), {
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

    router.delete(route('sales-returns.bulk-delete'), {
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
    router.get(route('sales-returns.index'), {
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
    router.get(route('sales-returns.index'), {
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
    <Head title="Retur Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Retur Penjualan</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="salesReturns"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'sales-returns.create' }"
                        :editRoute="null"
                        :deleteRoute="{ name: 'sales-returns.destroy' }"
                        :viewRoute="{ name: 'sales-returns.show' }"
                        :indexRoute="{ name: 'sales-returns.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="sales-returns"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="sales-returns.index"
                        @delete="deleteSalesReturn"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
