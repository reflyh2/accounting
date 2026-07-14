<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppViewButton from '@/Components/AppViewButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    suppliers: Object,
    filters: Object,
});

const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode Pemasok' },
    { key: 'name', label: 'Nama Pemasok' },
    { key: 'total_amount', label: 'Total Deposit' },
    { key: 'total_balance', label: 'Sisa Saldo' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    total_amount: (v) => formatNumber(v),
    total_balance: (v) => formatNumber(v),
};

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
                    :data="suppliers"
                    :filters="currentFilters"
                    :tableHeaders="tableHeaders"
                    :columnFormatters="columnFormatters"
                    :indexRoute="{ name: 'supplier-deposits.index' }"
                    routeName="supplier-deposits.index"
                    :enableBulkActions="false"
                    @filter="handleFilter"
                >
                    <template #custom_actions="{ item }">
                        <Link :href="route('supplier-deposits.supplier-detail', item.id)" class="mr-3">
                            <AppViewButton title="Detail Deposit" />
                        </Link>
                    </template>
                </AppDataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

