<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    payments: {
        type: Object,
        required: true
    },
    asset: {
        type: Object,
        required: true
    },
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'payment_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'period_start', label: 'Periode Mulai' },
    { key: 'period_end', label: 'Periode Selesai' },
    { key: 'payment_date', label: 'Tanggal Bayar' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'status', label: 'Status' },
    { key: 'notes', label: 'Catatan' },
    { key: 'actions', label: '' }
];

const indexRoute = { name: 'asset-rental-payments.index' };

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
        name: 'status',
        type: 'select',
        options: [
            { value: 'pending', label: 'Menunggu' },
            { value: 'paid', label: 'Lunas' },
            { value: 'overdue', label: 'Terlambat' }
        ],
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const columnFormatters = {
    period_start: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    period_end: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    payment_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    amount: (value) => formatNumber(value),
    status: (value) => ({
        'pending': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Menunggu</span>',
        'paid': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Lunas</span>',
        'overdue': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Terlambat</span>'
    })[value] || value
};

const sortableColumns = ['period_start', 'period_end', 'payment_date', 'amount', 'status'];
const defaultSort = { key: 'period_start', order: 'desc' };

function deletePayment(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-rental-payments.destroy', [props.asset.id, id]), {
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

    router.delete(route('asset-rental-payments.bulk-delete'), {
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
    router.get(route('asset-rental-payments.index', props.asset.id), {
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
    router.get(route('asset-rental-payments.index', props.asset.id), {
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
    <div class="text-gray-900">
        <AppDataTable
            :data="payments"
            :filters="currentFilters"
            :tableHeaders="tableHeaders"
            :columnFormatters="columnFormatters"
            :customFilters="customFilters"
            :sortable="sortableColumns"
            :defaultSort="defaultSort"
            :currentSort="currentSort"
            :perPage="perPage"
            :indexRoute="indexRoute"
            :createRoute="{ name: 'asset-rental-payments.create', params: { asset: asset.id } }"
            :editRoute="{ name: 'asset-rental-payments.edit', params: { asset: asset.id } }"
            :deleteRoute="{ name: 'asset-rental-payments.destroy', params: { asset: asset.id } }"
            @delete="deletePayment"
            @bulkDelete="handleBulkDelete"
            @sort="handleSort"
            @filter="handleFilter"
        >
            <template #custom_actions="{ item }">
                <button
                    v-if="item.status === 'pending'"
                    @click="$emit('update', item)"
                    class="text-green-600 hover:text-green-900 mr-3"
                >
                    Bayar
                </button>
            </template>
        </AppDataTable>
    </div>
</template> 