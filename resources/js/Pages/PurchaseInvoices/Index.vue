<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import { renderStatusPillHtml } from '@/utils/statusPillHtml';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';

const props = defineProps({
    invoices: Object,
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

const currentSort = ref({ key: props.sort || 'invoice_date', order: props.order || 'desc' });
const currentFilters = ref({ ...props.filters });

const tableHeaders = [
    { key: 'invoice_date', label: 'Tanggal' },
    { key: 'invoice_number', label: 'Nomor Faktur' },
    { key: 'purchase_orders', label: 'Nomor PO' },
    { key: 'partner.name', label: 'Supplier' },
    { key: 'status', label: 'Status' },
    { key: 'total_amount', label: 'Total' },
    { key: 'ppv_amount', label: 'PPV' },
    { key: 'actions', label: '' },
];

const sortableColumns = ['invoice_date', 'invoice_number', 'total_amount', 'status'];
const defaultSort = { key: 'invoice_date', order: 'desc' };

const columnFormatters = {
    invoice_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    total_amount: (value) => formatNumber(value ?? 0),
    ppv_amount: (value) => formatNumber(value ?? 0),
    purchase_orders: (value) => value ? '<ul class="list-disc">' + value.map(po => `<li class="mb-1"><a href="${route('purchase-orders.show', po.id)}" target="_blank" class="bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 text-xs px-2 py-1 rounded-full">${po.order_number}</a></li>`).join('') + '</ul>' : '-',
};

const columnRenderers = {
    status: (value) => renderStatusPillHtml(DocumentStatusKind.PURCHASE_INVOICE, value, 'sm'),
};

const customFilters = computed(() => [
    {
        name: 'search',
        type: 'text',
        label: 'Pencarian',
        placeholder: 'Nomor faktur atau PO',
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
        options: props.branches.map(branch => ({ value: branch.id, label: branch.name })),
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
    router.get(route('purchase-invoices.index'), {
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
    router.get(route('purchase-invoices.index'), {
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

function deleteInvoice(id) {
    router.delete(route('purchase-invoices.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Faktur Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Faktur Pembelian</h2>
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
                        :createRoute="{ name: 'purchase-invoices.create' }"
                        :editRoute="{ name: 'purchase-invoices.edit' }"
                        :deleteRoute="{ name: 'purchase-invoices.destroy' }"
                        :viewRoute="{ name: 'purchase-invoices.show' }"
                        :indexRoute="{ name: 'purchase-invoices.index' }"
                        :downloadOptions="[
                            { format: 'pdf', label: 'Download PDF' },
                            { format: 'xlsx', label: 'Download Excel' },
                            { format: 'csv', label: 'Download CSV' },
                        ]"
                        downloadBaseRoute="purchase-invoices"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="purchase-invoices.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor faktur atau PO..."
                        :enableBulkActions="false"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deleteInvoice"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('purchase-invoices.print', item.id)" target="_blank">
                                <AppPrintButton title="Print" />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

