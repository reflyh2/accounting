<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    locations: Object,
    branches: Array,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama Lokasi' },
    { key: 'type', label: 'Tipe' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'is_active', label: 'Status', format: (value) => value ? 'Aktif' : 'Tidak Aktif' },
    { key: 'actions', label: '' }
];

const typeOptions = [
    { value: 'warehouse', label: 'Warehouse' },
    { value: 'store', label: 'Store' },
    { value: 'room', label: 'Room' },
    { value: 'yard', label: 'Yard' },
    { value: 'vehicle', label: 'Vehicle' },
];

const customFilters = computed(() => [
    {
        name: 'branch_id',
        type: 'select',
        options: props.branches.map(branch => ({ value: branch.id, label: branch.name })),
        multiple: true,
        placeholder: 'Pilih cabang',
        label: 'Cabang'
    },
    {
        name: 'type',
        type: 'select',
        options: typeOptions,
        multiple: true,
        placeholder: 'Pilih tipe',
        label: 'Tipe'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['code', 'name', 'type', 'branch.name', 'is_active'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteLocation(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('locations.destroy', id), {
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

    router.delete(route('locations.bulk-delete'), {
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
    router.get(route('locations.index'), {
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
    router.get(route('locations.index'), {
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
    <Head title="Pengaturan Lokasi" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Lokasi</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="locations"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'locations.create' }"
                        :editRoute="{ name: 'locations.edit' }"
                        :deleteRoute="{ name: 'locations.destroy' }"
                        :viewRoute="{ name: 'locations.show' }"
                        :indexRoute="{ name: 'locations.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="locations"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="locations.index"
                        @delete="deleteLocation"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
