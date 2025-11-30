<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import DebtTabs from '@/Tabs/DebtTabs.vue';

const page = usePage();

const props = defineProps({
    items: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    paymentMethodOptions: Object,
});

const currentSort = ref({ key: props.sort || 'payment_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'payment_date', label: 'Tanggal' },
    { key: 'withdrawal_date', label: 'Tanggal Pencairan' },
    { key: 'number', label: 'Nomor' },
    { key: 'partner.name', label: 'Partner' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'payment_method', label: 'Metode' },
    { key: 'reference_number', label: 'Referensi' },
    { key: 'actions', label: '' },
];

const branchOptions = computed(() => props.branches.map(b => ({ value: b.id, label: b.name })));
const partnerOptions = computed(() => props.partners.map(p => ({ value: p.id, label: p.name })));

const customFilters = computed(() => [
    { name: 'from_date', type: 'date', placeholder: 'Dari Tanggal', label: 'Dari' },
    { name: 'to_date', type: 'date', placeholder: 'Sampai Tanggal', label: 'Sampai' },
    { name: 'company_id', type: 'select', options: props.companies.map(c => ({ value: c.id, label: c.name })), multiple: true, placeholder: 'Pilih Perusahaan', label: 'Perusahaan' },
    { name: 'branch_id', type: 'select', options: branchOptions.value, multiple: true, placeholder: 'Pilih Cabang', label: 'Cabang' },
    { name: 'partner_id', type: 'select', options: partnerOptions.value, multiple: true, placeholder: 'Pilih Partner', label: 'Partner' },
]);

const downloadOptions = [
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    payment_date: (v) => new Date(v).toLocaleDateString('id-ID'),
    payment_method: (v) => props.paymentMethodOptions[v] || v,
    withdrawal_date: (v) => v ? new Date(v).toLocaleDateString('id-ID') : '-',
    amount: (v) => `${formatNumber(v)}`,
};

const sortableColumns = ['payment_date', 'number', 'partner.name', 'branch.name', 'amount'];
const defaultSort = { key: 'payment_date', order: 'desc' };

function deleteItem(id) {
    router.delete(route('external-payable-payments.destroy', id), { preserveScroll: true, preserveState: true });
}

function handleBulkDelete(ids) {
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('external-payable-payments.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery, ids },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('external-payable-payments.index'), { ...route().params, ...currentFilters.value, sort: newSort.key, order: newSort.order, per_page: props.perPage }, { preserveState: true, preserveScroll: true, replace: true });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    if (newFilters.page) delete newFilters.page;
    router.get(route('external-payable-payments.index'), { ...currentFilters.value, sort: currentSort.value.key, order: currentSort.value.order, per_page: newFilters.per_page || props.perPage, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Pembayaran Hutang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Pembayaran Hutang</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <DebtTabs activeTab="external-payable-payments.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="items"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'external-payable-payments.create' }"
                        :editRoute="{ name: 'external-payable-payments.edit' }"
                        :deleteRoute="{ name: 'external-payable-payments.destroy' }"
                        :viewRoute="{ name: 'external-payable-payments.show' }"
                        :indexRoute="{ name: 'external-payable-payments.index' }"
                        :downloadOptions="downloadOptions"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="external-payable-payments.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor, partner, cabang..."
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


