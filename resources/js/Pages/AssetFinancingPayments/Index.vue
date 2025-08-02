<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    payments: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    creditors: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const tabs = [
    { label: 'Perjanjian Pembiayaan Aset', route: 'asset-financing-agreements.index', active: false },
    { label: 'Pembayaran Pembiayaan Aset', route: 'asset-financing-payments.index', active: true },
];

const currentSort = ref({ key: props.sort || 'payment_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'payment_date', label: 'Tanggal' },
    { key: 'number', label: 'Nomor' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'creditor.name', label: 'Kreditor' },
    { key: 'reference', label: 'Referensi' },
    { key: 'total_paid_amount', label: 'Total Dibayar' },
    { key: 'payment_method', label: 'Metode Pembayaran' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => 
    props.branches.map(branch => ({ value: branch.id, label: branch.name }))
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
        name: 'creditor_id',
        type: 'select',
        options: props.creditors.map(creditor => ({ value: creditor.id, label: creditor.name })),
        multiple: true,
        placeholder: 'Pilih Kreditor',
        label: 'Kreditor'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    payment_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    total_paid_amount: (value) => formatNumber(value),
};

const sortableColumns = ['payment_date', 'number', 'branch.name', 'creditor.name', 'reference', 'total_paid_amount', 'payment_method'];
const defaultSort = { key: 'payment_date', order: 'desc' };

function deletePayment(id) {
    router.delete(route('asset-financing-payments.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function handleBulkDelete(ids) {
    router.delete(route('asset-financing-payments.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            ids: ids,
        },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('asset-financing-payments.index'), {
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
    router.get(route('asset-financing-payments.index'), {
        ...newFilters,
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
    <Head title="Pembayaran Pembiayaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pembayaran Pembiayaan Aset</h2>
        </template>
        
        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="payments"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-financing-payments.create' }"
                        :editRoute="{ name: 'asset-financing-payments.edit' }"
                        :deleteRoute="{ name: 'asset-financing-payments.destroy' }"
                        :viewRoute="{ name: 'asset-financing-payments.show' }"
                        :indexRoute="{ name: 'asset-financing-payments.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-financing-payments"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-financing-payments.index"
                        @delete="deletePayment"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 