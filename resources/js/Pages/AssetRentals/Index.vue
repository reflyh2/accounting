<script setup>
import { ref, watch, computed } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import TabLinks from '@/Components/TabLinks.vue';

const page = usePage();

const props = defineProps({
    assetRentals: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const tabs = [
    { label: 'Pembelian Aset', route: 'asset-purchases.index', active: false },
    { label: 'Penyewaan Aset', route: 'asset-rentals.index', active: true },
    { label: 'Penjualan Aset', route: 'asset-sales.index', active: false },
    { label: 'Pembayaran Aset', route: 'asset-invoice-payments.index', active: false },
];

const currentSort = ref({ key: props.sort || 'invoice_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'invoice_date', label: 'Tanggal' },
    { key: 'number', label: 'Nomor Faktur' },
    { key: 'partner.name', label: 'Supplier' },
    { key: 'asset_invoice_details', label: 'Aset' },
    { key: 'due_date', label: 'Jatuh Tempo' },
    { key: 'status', label: 'Status' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'total_amount', label: 'Total' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
);

const partnerOptions = computed(() => 
    props.partners.map(partner => ({ value: partner.id, label: partner.name }))
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

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    invoice_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    due_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    total_amount: (value) => `${formatNumber(value)}`,
    status: (value) => {
        const statusMap = {
            'open': 'Terbuka',
            'paid': 'Lunas',
            'overdue': 'Terlambat',
            'cancelled': 'Dibatalkan',
            'voided': 'Dibatalkan',
            'closed': 'Ditutup',
            'partially_paid': 'Sebagian Lunas'
        };
        return statusMap[value] || value;
    }
};

const columnRenderers = {
    asset_invoice_details: (value) => value.map(detail => 
        `<a href="/assets/${detail.asset.id}" class="text-main-600 hover:text-main-800 hover:underline">${detail.asset.name}</a>`
    ).join(', '),
};

const sortableColumns = ['invoice_date', 'number', 'partner.name', 'due_date', 'status', 'branch.name', 'total_amount'];
const defaultSort = { key: 'invoice_date', order: 'desc' };

function deleteAssetRental(id) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-rentals.destroy', id), {
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

    router.delete(route('asset-rentals.bulk-delete'), {
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
    router.get(route('asset-rentals.index'), {
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
    router.get(route('asset-rentals.index'), {
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
    <Head title="Penyewaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Faktur Penyewaan Aset</h2>
        </template>

        <div class="mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="assetRentals"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnRenderers="columnRenderers"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-rentals.create' }"
                        :editRoute="{ name: 'asset-rentals.edit' }"
                        :deleteRoute="{ name: 'asset-rentals.destroy' }"
                        :viewRoute="{ name: 'asset-rentals.show' }"
                        :indexRoute="{ name: 'asset-rentals.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-rentals"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-rentals.index"
                        @delete="deleteAssetRental"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('asset-rentals.print', item.id)" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 