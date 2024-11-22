<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { formatNumber } from '@/utils/numberFormat';
import { PrinterIcon } from '@heroicons/vue/24/solid';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const props = defineProps({
    journals: Object,
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
    { label: 'Jurnal Umum', route: 'journals.index', active: true },
    { label: 'Penerimaan Kas', route: 'cash-receipt-journals.index', active: false },
    { label: 'Pengeluaran Kas', route: 'cash-payment-journals.index', active: false },
];

const tableHeaders = [
    { key: 'date', label: 'Tanggal' },
    { key: 'journal_number', label: 'Nomor Jurnal' },
    { key: 'reference_number', label: 'Nomor Referensi' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'description', label: 'Catatan' },
    { key: 'journal_entries_sum_primary_currency_debit', label: 'Total' },
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
    journal_entries_sum_primary_currency_debit: (value) => formatNumber(value),
};

const sortableColumns = ['date', 'journal_number', 'reference_number', 'branches.name', 'description', 'journal_entries_sum_primary_currency_debit'];
const defaultSort = { key: 'date', order: 'desc' };

function deleteJournal(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('journals.destroy', id), {
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

    router.delete(route('journals.bulk-delete'), {
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
    router.get(route('journals.index'), {
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
    router.get(route('journals.index'), {
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
    <Head title="Jurnal Umum" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Jurnal Umum</h2>
        </template>

        <div class="min-w-min md:min-w-max mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="journals"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'journals.create' }"
                        :editRoute="{ name: 'journals.edit' }"
                        :deleteRoute="{ name: 'journals.destroy' }"
                        :viewRoute="{ name: 'journals.show' }"
                        :indexRoute="{ name: 'journals.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="journals"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="journals.index"
                        @delete="deleteJournal"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('journals.print', item.id)" class="mr-3" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>