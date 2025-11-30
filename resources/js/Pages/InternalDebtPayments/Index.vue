<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import InternalDebtTabs from '@/Tabs/InternalDebtTabs.vue';

const props = defineProps({
    items: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    counterpartyBranches: Array,
    perPage: [String, Number],
    sort: String,
    order: String,  
    paymentStatusOptions: Object,
    paymentStatusStyles: Object,
    paymentMethodOptions: Object,
});

const currentSort = ref({ key: props.sort || 'payment_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'payment_date', label: 'Tanggal' },
    { key: 'number', label: 'Nomor' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'payment_method', label: 'Metode' },
    { key: 'reference_number', label: 'Referensi' },
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
    { name: 'branch_id', type: 'select', options: branchOptions.value, multiple: true, placeholder: 'Pilih Cabang', label: 'Cabang' },
    { name: 'counterparty_company_id', type: 'select', options: companyOptions.value, multiple: true, placeholder: 'Pilih Perusahaan (Pemberi)', label: 'Perusahaan (Pemberi)' },
    { name: 'counterparty_branch_id', type: 'select', options: counterpartyBranchOptions.value, multiple: true, placeholder: 'Pilih Cabang (Pemberi)', label: 'Cabang (Pemberi)' },
]);

const downloadOptions = [
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    payment_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    payment_method: (value) => props.paymentMethodOptions[value] || value,
    amount: (value) => Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0)),
};

const columnRenderers = {
    status: (value) => {
        const style = props.paymentStatusStyles?.[value] || { label: value, class: 'bg-gray-100 text-gray-800' };
        return `<span class="px-2 py-1 text-xs font-medium rounded-full ${style.class}">${props.paymentStatusOptions[value] || value}</span>`;
    }
};

const sortableColumns = ['payment_date', 'number', 'branch.name', 'amount', 'payment_method', 'status'];
const defaultSort = { key: 'payment_date', order: 'desc' };

function deleteItem(id) {
    const currentQuery = window.location.search ? window.location.search.substring(1) : '';
    router.delete(route('internal-debt-payments.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery },
    });
}

function handleBulkDelete(ids) {
    const currentQuery = window.location.search ? window.location.search.substring(1) : '';
    router.delete(route('internal-debt-payments.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery, ids },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('internal-debt-payments.index'), {
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
    router.get(route('internal-debt-payments.index'), {
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
    <Head title="Pembayaran Hutang/Piutang Internal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pembayaran Hutang/Piutang Internal</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <InternalDebtTabs activeTab="internal-debt-payments.index" />
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="items"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'internal-debt-payments.create' }"
                        :editRoute="{ name: 'internal-debt-payments.edit' }"
                        :deleteRoute="{ name: 'internal-debt-payments.destroy' }"
                        :viewRoute="{ name: 'internal-debt-payments.show' }"
                        :indexRoute="{ name: 'internal-debt-payments.index' }"
                        :downloadOptions="downloadOptions"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="internal-debt-payments.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor, referensi, catatan..."
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


