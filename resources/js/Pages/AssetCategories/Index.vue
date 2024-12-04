<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    categories: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Aset', route: 'assets.index', active: false },
    { label: 'Kategori Aset', route: 'asset-categories.index', active: true },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Kategori' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'assets_count', label: 'Jumlah Aset' },
    { key: 'actions', label: '' }
];

const customFilters = computed(() => [
    {
        name: 'search',
        type: 'text',
        placeholder: 'Cari kategori...',
        label: 'Pencarian'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'description', 'assets_count'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteCategory(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-categories.destroy', id), {
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

    router.delete(route('asset-categories.bulk-delete'), {
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
    router.get(route('asset-categories.index'), {
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
    router.get(route('asset-categories.index'), {
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
    <Head title="Kategori Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Kategori Aset</h2>
        </template>

        <TabLinks :tabs="tabs" />

        <div class="min-w-min md:min-w-max mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="categories"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-categories.create' }"
                        :viewRoute="{ name: 'asset-categories.show' }"
                        :editRoute="{ name: 'asset-categories.edit' }"
                        :deleteRoute="{ name: 'asset-categories.destroy' }"
                        :indexRoute="{ name: 'asset-categories.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-categories"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-categories.index"
                        @delete="deleteCategory"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 