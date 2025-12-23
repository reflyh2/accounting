<script setup>
import { ref, computed, h } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';
import { renderStatusPillHtml } from '@/utils/statusPillHtml';

const props = defineProps({
    invoices: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    companies: Array,
    branches: Array,
    customers: Array,
    statusOptions: Object,
    perPage: [Number, String],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'invoice_date', order: props.order || 'desc' });
const currentFilters = ref({ ...props.filters });

const tableHeaders = [
    { key: 'invoice_date', label: 'Tanggal' },
    { key: 'invoice_number', label: 'Nomor Faktur' },
    { key: 'sales_orders', label: 'Sales Order(s)' },
    { key: 'customer_name', label: 'Customer' },
    { key: 'status', label: 'Status' },
    { key: 'total_amount', label: 'Total' },
    { key: 'actions', label: '' },
];

const sortableColumns = ['invoice_date', 'invoice_number', 'total_amount', 'status'];
const defaultSort = { key: 'invoice_date', order: 'desc' };

const columnFormatters = {
    invoice_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    total_amount: (value) => formatNumber(value ?? 0),
    sales_orders: (value) => value ? '<ul class="list-disc">' + value.map(so => `<li class="mb-1"><a href="${route('sales-orders.show', so.id)}" target="_blank" class="bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 text-xs px-2 py-1 rounded-full">${so.order_number}</a></li>`).join('') + '</ul>' : '-',
};

const columnRenderers = {
    status: (value) => renderStatusPillHtml(DocumentStatusKind.SALES_INVOICE, value, 'sm'),
};

const customFilters = computed(() => [
    {
        name: 'search',
        type: 'text',
        placeholder: 'Cari nomor faktur, nomor SO...',
        label: 'Pencarian'
    },
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
        options: props.branches.map(branch => ({ value: branch.id, label: branch.name })),
        multiple: true,
        placeholder: 'Pilih cabang',
        label: 'Cabang'
    },
    {
        name: 'partner_id',
        type: 'select',
        options: props.customers.map(customer => ({ value: customer.id, label: customer.name })),
        multiple: true,
        placeholder: 'Pilih customer',
        label: 'Customer'
    },
    {
        name: 'status',
        type: 'select',
        options: Object.entries(props.statusOptions || {}).map(([value, label]) => ({ value, label })),
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

function deleteInvoice(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('sales-invoices.destroy', id), {
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

    router.delete(route('sales-invoices.bulk-delete'), {
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
    router.get(route('sales-invoices.index'), {
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
    router.get(route('sales-invoices.index'), {
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
    <Head title="Faktur Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Faktur Penjualan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="invoices"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'sales-invoices.create' }"
                        :editRoute="{ name: 'sales-invoices.edit' }"
                        :deleteRoute="{ name: 'sales-invoices.destroy' }"
                        :viewRoute="{ name: 'sales-invoices.show' }"
                        :indexRoute="{ name: 'sales-invoices.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="sales-invoices"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="sales-invoices.index"
                        @delete="deleteInvoice"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <Link
                                v-if="item.status === 'draft'"
                                :href="route('sales-invoices.post', item.id)"
                                method="post"
                                as="button"
                                class="text-blue-600 hover:text-blue-900 text-sm"
                            >
                                Post
                            </Link>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
