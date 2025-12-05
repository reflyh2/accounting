<script setup>
import { ref, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const page = usePage();

const props = defineProps({
    assetPurchases: Object, // Renamed from journals
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array, // Added partners for filtering
    perPage: [String, Number],
    sort: String,
    order: String,
    statusOptions: Object,
});

const tabs = [
    { label: 'Pembelian Aset', route: 'asset-purchases.index', active: true },
    { label: 'Penyewaan Aset', route: 'asset-rentals.index', active: false },
    { label: 'Penjualan Aset', route: 'asset-sales.index', active: false },
    { label: 'Pembayaran Aset', route: 'asset-invoice-payments.index', active: false },
];

const currentSort = ref({ key: props.sort || 'invoice_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

// Removed tabs definition
const tableHeaders = [
    { key: 'invoice_date', label: 'Tgl Faktur' },
    { key: 'number', label: 'Nomor Faktur' },
    { key: 'partner.name', label: 'Supplier' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'asset_invoice_details', label: 'Aset' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'total_amount', label: 'Total' },
    { key: 'due_date', label: 'Jatuh Tempo' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

const partnerOptions = computed(() =>
    props.partners.map(partner => ({ value: partner.id, label: partner.name }))
);

// Define custom filters specific to asset purchases
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
        name: 'company_id',
        type: 'select',
        options: props.companies.map(company => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih Perusahaan',
        label: 'Perusahaan'
    },
    {
        name: 'branch_id',
        type: 'select',
        options: branchOptions.value,
        multiple: true,
        placeholder: 'Pilih Cabang',
        label: 'Cabang'
    },
    {
        name: 'partner_id',
        type: 'select',
        options: partnerOptions.value,
        multiple: true,
        placeholder: 'Pilih Supplier',
        label: 'Supplier'
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
};

const columnRenderers = {
    asset_invoice_details: (value) => value.map(detail => 
        `<a href="/assets/${detail.asset.id}" class="text-main-600 hover:text-main-800 hover:underline">${detail.asset.name}</a>`
    ).join(', '),    
    status: (value) => {
        const statusColors = {
            paid: 'bg-green-100 text-green-800',
            financed: 'bg-blue-100 text-blue-800',
            overdue: 'bg-orange-100 text-orange-800',
            cancelled: 'bg-red-100 text-red-800',
            defaulted: 'bg-red-100 text-red-800',
            default: 'bg-gray-100 text-gray-800'
        };
        const color = statusColors[value] || statusColors.default;
        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${color}">${props.statusOptions[value] || value}</span>`;
    },
};

// Define sortable columns
const sortableColumns = ['invoice_date', 'number', 'partner.name', 'branch.name', 'due_date', 'total_amount', 'status', 'asset_invoice_details'];
const defaultSort = { key: 'invoice_date', order: 'desc' };

// --- Event Handlers ---
function deleteItem(id) { // Renamed from deleteJournal
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-purchases.destroy', id), {
        preserveScroll: true,
        preserveState: true, // Keep state on delete
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleBulkDelete(ids) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-purchases.bulk-delete'), {
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
    router.get(route('asset-purchases.index'), {
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
    router.get(route('asset-purchases.index'), {
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
    <Head title="Pembelian Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Faktur Pembelian Aset</h2>
        </template>

        <div class="mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="assetPurchases"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-purchases.create' }"
                        :editRoute="{ name: 'asset-purchases.edit' }"
                        :deleteRoute="{ name: 'asset-purchases.destroy' }"
                        :viewRoute="{ name: 'asset-purchases.show' }"
                        :indexRoute="{ name: 'asset-purchases.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-purchases"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-purchases.index"
                        itemKey="id"
                        searchPlaceholder="Cari no. faktur, partner, cabang..."
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                             <a :href="route('asset-purchases.print', item.id)" target="_blank" class="ml-2">
                                <AppPrintButton title="Cetak Faktur Ini" />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 