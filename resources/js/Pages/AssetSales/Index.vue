<script setup>
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import TabLinks from '@/Components/TabLinks.vue';

const page = usePage();

const props = defineProps({
    sales: Object,
    filters: Object,
    partners: Array,
    statuses: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const tabs = [
    { label: 'Pembelian Aset', route: 'asset-purchases.index', active: false },
    { label: 'Penyewaan Aset', route: 'asset-rentals.index', active: false },
    { label: 'Penjualan Aset', route: 'asset-sales.index', active: true },
    { label: 'Pembayaran Aset', route: 'asset-invoice-payments.index', active: false },
];

const currentSort = ref({ key: props.sort || 'invoice_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'invoice_date', label: 'Tgl Faktur' },
    { key: 'number', label: 'Nomor Faktur' },
    { key: 'partner.name', label: 'Customer' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'asset_invoice_details', label: 'Aset' },
    { key: 'due_date', label: 'Jatuh Tempo' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'total_amount', label: 'Total' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const partnerOptions = computed(() =>
    props.partners.map(partner => ({ value: partner.id, label: partner.name }))
);

const statusOptions = computed(() =>
    Object.entries(props.statuses).map(([value, label]) => ({ value, label }))
);

// Define custom filters specific to asset sales
const customFilters = computed(() => [
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal Faktur',
        label: 'Dari Tanggal'
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal Faktur',
        label: 'Sampai Tanggal'
    },
    {
        name: 'partner_id',
        type: 'select',
        options: partnerOptions.value,
        multiple: true,
        placeholder: 'Pilih Customer',
        label: 'Customer'
    },
    {
        name: 'status',
        type: 'select',
        options: statusOptions.value,
        multiple: true,
        placeholder: 'Pilih Status',
        label: 'Status'
    }
]);

// Define download options
const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

// Define column formatters
const columnFormatters = {
    invoice_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    due_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    total_amount: (value) => `${formatNumber(value)}`,
    status: (value) => {
        const statusLabels = {
            'open': 'Belum Dibayar',
            'partially_paid': 'Dibayar Sebagian',
            'paid': 'Lunas'
        };
        return statusLabels[value] || value;
    }
};

const columnRenderers = {
    asset_invoice_details: (value) => value.map(detail => 
        `<a href="/assets/${detail.asset.id}" class="text-main-600 hover:text-main-800 hover:underline">${detail.asset.name}</a>`
    ).join(', '),
};

// Define sortable columns
const sortableColumns = ['invoice_date', 'number', 'partner.name', 'branch.name', 'due_date', 'total_amount', 'status'];
const defaultSort = { key: 'invoice_date', order: 'desc' };

// --- Event Handlers ---
function deleteItem(id) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-sales.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleBulkDelete(ids) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-sales.bulk-delete'), {
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
    router.get(route('asset-sales.index'), {
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
    // Reset page to 1 when filters change
    if (newFilters.page) {
        delete newFilters.page;
    }
    router.get(route('asset-sales.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
        page: 1, // Go to page 1 on filter change
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Penjualan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Faktur Penjualan Aset</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="sales"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-sales.create' }"
                        :editRoute="{ name: 'asset-sales.edit' }"
                        :deleteRoute="{ name: 'asset-sales.destroy' }"
                        :viewRoute="{ name: 'asset-sales.show' }"
                        :indexRoute="{ name: 'asset-sales.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-sales"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-sales.index"
                        itemKey="id"
                        searchPlaceholder="Cari no. faktur, customer, cabang..."
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                             <a :href="route('asset-sales.print', item.id)" target="_blank" class="ml-2">
                                <AppPrintButton title="Cetak Faktur Ini" />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 