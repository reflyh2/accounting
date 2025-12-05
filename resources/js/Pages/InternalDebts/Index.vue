<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import InternalDebtTabs from '@/Tabs/InternalDebtTabs.vue';

const page = usePage();

const props = defineProps({
    debts: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    counterpartyBranches: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    statusOptions: Object,
    statusStyles: Object,
});

const currentSort = ref({ key: props.sort || 'issue_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'issue_date', label: 'Tgl Terbit' },
    { key: 'number', label: 'Nomor' },
    { key: 'branch', label: 'Peminjam' },
    { key: 'counterparty_branch', label: 'Pemberi Pinjaman' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'due_date', label: 'Jatuh Tempo' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const companyOptions = computed(() =>
    props.companies.map(c => ({ value: c.id, label: c.name }))
);
const branchOptions = computed(() =>
    props.branches.map(b => ({ value: b.id, label: b.name }))
);
const counterpartyBranchOptions = computed(() =>
    props.counterpartyBranches.map(b => ({ value: b.id, label: b.name }))
);

const customFilters = computed(() => [
    { name: 'from_date', type: 'date', placeholder: 'Dari Tanggal', label: 'Dari Tanggal' },
    { name: 'to_date', type: 'date', placeholder: 'Sampai Tanggal', label: 'Sampai Tanggal' },
    { name: 'company_id', type: 'select', options: companyOptions.value, multiple: true, placeholder: 'Pilih Perusahaan', label: 'Perusahaan' },
    { name: 'branch_id', type: 'select', options: branchOptions.value, multiple: true, placeholder: 'Pilih Cabang (Peminjam)', label: 'Cabang (Peminjam)' },
    { name: 'counterparty_company_id', type: 'select', options: companyOptions.value, multiple: true, placeholder: 'Pilih Perusahaan (Pemberi)', label: 'Perusahaan (Pemberi)' },
    { name: 'counterparty_branch_id', type: 'select', options: counterpartyBranchOptions.value, multiple: true, placeholder: 'Pilih Cabang (Pemberi)', label: 'Cabang (Pemberi)' },
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' },
];

const columnFormatters = {
    branch: (value) => `${value.branch_group.company.name} | ${value.name}`,
    counterparty_branch: (value) => `${value.branch_group.company.name} | ${value.name}`,
    issue_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    due_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    amount: (value) => Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0)),
};

const columnRenderers = {
    status: (value) => {
        const color = {label: props.statusStyles[value]?.label || value, class: props.statusStyles[value]?.class || 'bg-gray-100 text-gray-800'};
        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${color.class}">${props.statusOptions[value] || value}</span>`;
    },
};

const sortableColumns = ['issue_date', 'number', 'branch.name', 'counterparty_branch.name', 'due_date', 'amount', 'status'];
const defaultSort = { key: 'issue_date', order: 'desc' };

function deleteItem(id) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('internal-debts.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery },
    });
}

function handleBulkDelete(ids) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('internal-debts.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery, ids },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('internal-debts.index'), {
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
    if (newFilters.page) delete newFilters.page;
    router.get(route('internal-debts.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
        page: 1,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Hutang / Piutang Internal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Hutang / Piutang Internal</h2>
        </template>

        <div class="mx-auto">
            <InternalDebtTabs activeTab="internal-debts.index" />
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="debts"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'internal-debts.create' }"
                        :editRoute="{ name: 'internal-debts.edit' }"
                        :deleteRoute="{ name: 'internal-debts.destroy' }"
                        :viewRoute="{ name: 'internal-debts.show' }"
                        :indexRoute="{ name: 'internal-debts.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="internal-debts"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="internal-debts.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor, catatan..."
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    </template>


