<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    shippingProviders: Object,
    filters: Object,
    perPage: [String, Number],
    sortColumn: String,
    sortOrder: String,
    typeOptions: Object,
});

const currentSort = ref({ key: props.sortColumn || 'code', order: props.sortOrder || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'type', label: 'Tipe' },
    { key: 'contact_person', label: 'Kontak' },
    { key: 'phone', label: 'Telepon' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    type: (value) => props.typeOptions[value] || value,
    is_active: (value) => value ? 'Aktif' : 'Tidak Aktif',
};

const columnRenderers = {
    type: (value, row) => {
        const typeClass = value === 'internal'
            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        const label = props.typeOptions[value] || value;
        return `<span class="px-2 py-1 text-xs font-medium rounded ${typeClass}">${label}</span>`;
    },
    is_active: (value) => {
        if (value) {
            return '<span class="px-2 py-1 text-xs font-medium rounded bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">Aktif</span>';
        }
        return '<span class="px-2 py-1 text-xs font-medium rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Tidak Aktif</span>';
    },
};

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['code', 'name', 'type'];
const defaultSort = { key: 'code', order: 'asc' };

const filterFields = [
    {
        key: 'search',
        type: 'text',
        label: 'Cari',
        placeholder: 'Cari kode, nama, kontak, atau telepon...'
    },
    {
        key: 'type',
        type: 'select',
        label: 'Tipe',
        options: [
            { value: '', label: 'Semua Tipe' },
            ...Object.entries(props.typeOptions).map(([value, label]) => ({ value, label }))
        ]
    },
    {
        key: 'is_active',
        type: 'select',
        label: 'Status',
        options: [
            { value: '', label: 'Semua Status' },
            { value: '1', label: 'Aktif' },
            { value: '0', label: 'Tidak Aktif' }
        ]
    }
];

function deleteShippingProvider(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('shipping-providers.destroy', id), {
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

    router.delete(route('shipping-providers.bulk-delete'), {
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
    router.get(route('shipping-providers.index'), {
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
    router.get(route('shipping-providers.index'), {
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
    <Head title="Penyedia Pengiriman" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Penyedia Pengiriman</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="shippingProviders"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :createRoute="{ name: 'shipping-providers.create' }"
                        :editRoute="{ name: 'shipping-providers.edit' }"
                        :deleteRoute="{ name: 'shipping-providers.destroy' }"
                        :viewRoute="{ name: 'shipping-providers.show' }"
                        :indexRoute="{ name: 'shipping-providers.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="shipping-providers"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        :filterFields="filterFields"
                        routeName="shipping-providers.index"
                        @delete="deleteShippingProvider"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
