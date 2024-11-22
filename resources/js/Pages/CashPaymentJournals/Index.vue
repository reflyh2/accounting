<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const props = defineProps({
    cashPaymentJournals: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tabs = [
   { label: 'Jurnal Umum', route: 'journals.index', active: false },
   { label: 'Penerimaan Kas', route: 'cash-receipt-journals.index', active: false },
   { label: 'Pengeluaran Kas', route: 'cash-payment-journals.index', active: true },
];

const tableHeaders = [
    { key: 'date', label: 'Tanggal' },
    { key: 'journal_number', label: 'Nomor Jurnal' },
    { key: 'reference_number', label: 'Nomor Referensi' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'description', label: 'Catatan' },
    { key: 'journal_entries_sum_primary_currency_credit', label: 'Total' },
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
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    date: (value) => new Date(value).toLocaleDateString('id-ID'),
    journal_entries_sum_primary_currency_credit: (value) => formatNumber(value),
};

const sortableColumns = ['date', 'journal_number', 'reference_number', 'branch.name', 'description', 'journal_entries_sum_amount'];
const defaultSort = { key: 'date', order: 'desc' };

function deleteCashPaymentJournal(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('cash-payment-journals.destroy', id), {
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

    router.delete(route('cash-payment-journals.bulk-delete'), {
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
    router.get(route('cash-payment-journals.index'), {
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
    router.get(route('cash-payment-journals.index'), {
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
    <Head title="Pengeluaran Kas" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengeluaran Kas</h2>
        </template>

        <div class="min-w-min md:min-w-max mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="cashPaymentJournals"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'cash-payment-journals.create' }"
                        :editRoute="{ name: 'cash-payment-journals.edit' }"
                        :deleteRoute="{ name: 'cash-payment-journals.destroy' }"
                        :viewRoute="{ name: 'cash-payment-journals.show' }"
                        :indexRoute="{ name: 'cash-payment-journals.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="cash-payment-journals"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="cash-payment-journals.index"
                        @delete="deleteCashPaymentJournal"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('cash-payment-journals.print', item.id)" class="mr-3" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>