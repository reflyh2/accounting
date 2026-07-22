<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppBackLink from '@/Components/AppBackLink.vue';


const props = defineProps({
    partner: Object,
    deposits: Object,
    filters: Object,
    companies: Array,
    statusOptions: Array,
});

const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'deposit_date', label: 'Tanggal' },
    { key: 'deposit_number', label: 'No. Deposit' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'balance', label: 'Saldo Sisa' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    deposit_date: (v) => v ? new Date(v).toLocaleDateString('id-ID') : '-',
    amount: (v) => formatNumber(v),
    balance: (v) => formatNumber(v),
};

const columnRenderers = {
    status: (v) => {
        const label = { open: 'Saldo Tersedia', exhausted: 'Habis Dipakai', refunded: 'Direfund' }[v] || v;
        const cls = {
            open: 'bg-green-100 text-green-800',
            exhausted: 'bg-gray-200 text-gray-700',
            refunded: 'bg-amber-100 text-amber-800',
        }[v] || 'bg-gray-100 text-gray-800';
        return `<span class="px-2 py-0.5 rounded text-xs ${cls}">${label}</span>`;
    },
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
    router.get(route('supplier-deposits.supplier-detail', props.partner.id), newFilters, {
        preserveState: true, preserveScroll: true, replace: true,
    });
}
</script>

<template>
    <Head :title="`Detail Deposit - ${partner.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2>Detail Deposit:</h2>
            </div>
        </template>

        <div class="mx-auto space-y-4">
            <!-- Supplier Info Card -->
            <div class="bg-white p-6 shadow-sm sm:rounded border border-gray-200">
                <AppBackLink :href="route('supplier-deposits.index')" class="mb-6" text="Kembali ke Daftar Pemasok" />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-gray-500 block text-xs font-semibold uppercase tracking-wider">Nama Pemasok</span>
                        <span class="text-lg font-bold text-gray-900">{{ partner.name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-xs font-semibold uppercase tracking-wider">Kode Pemasok</span>
                        <span class="text-lg font-bold text-gray-900">{{ partner.code }}</span>
                    </div>
                </div>
            </div>

            <!-- Deposits Table -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <AppDataTable
                    :data="deposits"
                    :filters="currentFilters"
                    :tableHeaders="tableHeaders"
                    :columnFormatters="columnFormatters"
                    :columnRenderers="columnRenderers"
                    :customFilters="customFilters"
                    :createRoute="{ name: 'supplier-deposits.create', params: { partner_id: partner.id } }"
                    createButtonLabel="Catat Deposit Baru"
                    :viewRoute="{ name: 'supplier-deposits.show' }"
                    :indexRoute="{ name: 'supplier-deposits.supplier-detail', params: { partner: partner.id } }"
                    routeName="supplier-deposits.supplier-detail"
                    @filter="handleFilter"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
