<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    deposits: Object,
    filters: Object,
    companies: Array,
    suppliers: Array,
    statusOptions: Array,
});

const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'deposit_date', label: 'Tanggal' },
    { key: 'deposit_number', label: 'No. Deposit' },
    { key: 'partner.name', label: 'Pemasok' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'balance', label: 'Saldo Sisa' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    deposit_date: (v) => v ? new Date(v).toLocaleDateString('id-ID') : '-',
    amount: (v) => formatNumber(v),
    balance: (v) => formatNumber(v),
    status: (v) => ({ open: 'Saldo Tersedia', exhausted: 'Habis Dipakai', refunded: 'Direfund' }[v] || v),
};

const customFilters = [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map((c) => ({ value: c.id, label: c.name })),
        multiple: true,
        placeholder: 'Filter perusahaan',
        label: 'Perusahaan',
    },
    {
        name: 'partner_id',
        type: 'select',
        options: props.suppliers.map((s) => ({ value: s.id, label: `${s.code} — ${s.name}` })),
        multiple: true,
        placeholder: 'Filter pemasok',
        label: 'Pemasok',
    },
    {
        name: 'status',
        type: 'select',
        options: props.statusOptions,
        multiple: true,
        placeholder: 'Filter status',
        label: 'Status',
    },
];

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('supplier-deposits.index'), newFilters, {
        preserveState: true, preserveScroll: true, replace: true,
    });
}
</script>

<template>
    <Head title="Deposit Pemasok" />

    <AuthenticatedLayout>
        <template #header><h2>Deposit Pemasok</h2></template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <AppDataTable
                    :data="deposits"
                    :filters="currentFilters"
                    :tableHeaders="tableHeaders"
                    :columnFormatters="columnFormatters"
                    :customFilters="customFilters"
                    :createRoute="{ name: 'supplier-deposits.create' }"
                    :viewRoute="{ name: 'supplier-deposits.show' }"
                    :indexRoute="{ name: 'supplier-deposits.index' }"
                    routeName="supplier-deposits.index"
                    @filter="handleFilter"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
