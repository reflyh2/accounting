<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TaxConfigTabs from '@/Tabs/TaxConfigTabs.vue';

const props = defineProps({
    categories: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    appliesTo: Object,
    behaviors: Object,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'applies_to', label: 'Berlaku Untuk' },
    { key: 'default_behavior', label: 'Perilaku Default' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    applies_to: (value) => props.appliesTo[value],
    default_behavior: (value) => props.behaviors[value],
};

const customFilters = computed(() => [
    {
        name: 'applies_to',
        type: 'select',
        options: Object.entries(props.appliesTo).map(([value, label]) => ({ value, label })),
        multiple: true,
        placeholder: 'Pilih tipe',
        label: 'Berlaku Untuk'
    },
    {
        name: 'default_behavior',
        type: 'select',
        options: Object.entries(props.behaviors).map(([value, label]) => ({ value, label })),
        multiple: true,
        placeholder: 'Pilih perilaku',
        label: 'Perilaku'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['code', 'name', 'applies_to', 'default_behavior'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('tax-categories.destroy', id), {
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

    router.delete(route('tax-categories.bulk-delete'), {
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
    router.get(route('tax-categories.index'), {
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
    router.get(route('tax-categories.index'), {
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
    <Head title="Kategori Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Kategori Pajak</h2>
        </template>

        <div class="mx-auto">
            <TaxConfigTabs activeTab="tax-categories.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="categories"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'tax-categories.create' }"
                        :editRoute="{ name: 'tax-categories.edit' }"
                        :deleteRoute="{ name: 'tax-categories.destroy' }"
                        :viewRoute="{ name: 'tax-categories.show' }"
                        :indexRoute="{ name: 'tax-categories.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="tax-categories"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="tax-categories.index"
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
