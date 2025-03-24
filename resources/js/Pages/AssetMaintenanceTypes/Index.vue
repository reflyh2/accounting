<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import AppSelect from '@/Components/AppSelect.vue';

const props = defineProps({
    maintenanceTypes: Object,
    categories: Array,
    companies: Array,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Aset', route: 'assets.index', active: false },
    { label: 'Kategori Aset', route: 'asset-categories.index', active: false },
    { label: 'Tipe Pemeliharaan', route: 'asset-maintenance-types.index', active: true },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Tipe' },
    { key: 'asset_category.name', label: 'Kategori Aset' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'maintenance_interval', label: 'Interval' },
    { key: 'maintenance_records_count', label: 'Jumlah Catatan' },
    { key: 'actions', label: '' }
];

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'asset_category.name', 'description', 'maintenance_interval', 'maintenance_records_count'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteMaintenanceType(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-maintenance-types.destroy', id), {
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

    router.delete(route('asset-maintenance-types.bulk-delete'), {
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
    router.get(route('asset-maintenance-types.index'), {
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
    router.get(route('asset-maintenance-types.index'), {
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

const categoryOptions = computed(() => {
    return props.categories.map(category => ({
        value: category.id,
        label: category.name
    }));
});
</script>

<template>
    <Head title="Tipe Pemeliharaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Tipe Pemeliharaan Aset</h2>
        </template>

        <TabLinks :tabs="tabs" />

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="maintenanceTypes"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'asset-maintenance-types.create' }"
                        :viewRoute="{ name: 'asset-maintenance-types.show' }"
                        :editRoute="{ name: 'asset-maintenance-types.edit' }"
                        :deleteRoute="{ name: 'asset-maintenance-types.destroy' }"
                        :indexRoute="{ name: 'asset-maintenance-types.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-maintenance-types"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-maintenance-types.index"
                        @delete="deleteMaintenanceType"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #filters>
                            <div class="mr-4 flex-grow md:flex-grow-0">
                                <AppSelect
                                    v-model="currentFilters.category_id"
                                    :options="categoryOptions"
                                    placeholder="Filter Kategori"
                                    @update:modelValue="handleFilter(currentFilters)"
                                    class="w-full"
                                    clearable
                                />
                            </div>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 