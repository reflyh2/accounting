<script setup>
import { ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InventoryTabs from '@/Tabs/InventoryTabs.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    transactions: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    locations: {
        type: Array,
        default: () => [],
    },
    perPage: [Number, String],
});

const currentSort = ref({ key: 'transaction_date', order: 'desc' });
const currentFilters = ref({ ...props.filters });

const tableHeaders = [
    { key: 'transaction_date', label: 'Tanggal' },
    { key: 'transaction_number', label: 'Nomor' },
    { key: 'location_from', label: 'Lokasi Sumber' },
    { key: 'source_type', label: 'Referensi' },
    { key: 'totals.quantity', label: 'Total Qty' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    transaction_date: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    'totals.quantity': (value) => (value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }),
};

const columnRenderers = {
    location_from: (value) => value ? `${value.code} — ${value.name}` : '-',
};

const customFilters = computed(() => [
    {
        name: 'search',
        type: 'text',
        placeholder: 'Cari nomor atau referensi',
        label: 'Pencarian',
    },
    {
        name: 'date_from',
        type: 'date',
        placeholder: 'Dari tanggal',
        label: 'Tanggal Mulai',
    },
    {
        name: 'date_to',
        type: 'date',
        placeholder: 'Sampai tanggal',
        label: 'Tanggal Selesai',
    },
    {
        name: 'location_id',
        type: 'select',
        options: props.locations.map(location => ({ value: location.id, label: location.label })),
        multiple: false,
        placeholder: 'Pilih lokasi',
        label: 'Lokasi',
    },
]);

const sortableColumns = ['transaction_date', 'transaction_number', 'totals.quantity'];
const defaultSort = { key: 'transaction_date', order: 'desc' };

function deleteItem(id) {
    router.delete(route('inventory.shipments.destroy', id), {
        preserveScroll: true,
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('inventory.shipments.index'), {
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
    router.get(route('inventory.shipments.index'), {
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
    <Head title="Pengeluaran Barang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Pengeluaran Barang</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <InventoryTabs activeTab="inventory.shipments.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="transactions"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'inventory.shipments.create' }"
                        :editRoute="{ name: 'inventory.shipments.edit' }"
                        :deleteRoute="{ name: 'inventory.shipments.destroy' }"
                        :viewRoute="{ name: 'inventory.shipments.show' }"
                        :indexRoute="{ name: 'inventory.shipments.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="inventory.shipments.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor atau referensi..."
                        @delete="deleteItem"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

