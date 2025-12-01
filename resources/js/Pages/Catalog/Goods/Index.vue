<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    items: Object,
    filters: Object,
    categories: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'category.name', label: 'Category' },
    { key: 'default_uom.code', label: 'Default UOM' },
    { key: 'tax_category.name', label: 'Tax' },
    { key: 'is_active', label: 'Active' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    is_active: (value) => value ? 'Yes' : 'No',
};

const sortableColumns = ['code','name','is_active'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('catalog.goods.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('catalog.goods.index'), {
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
    router.get(route('catalog.goods.index'), {
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

const customFilters = [
    {
        name: 'product_category_id',
        type: 'select',
        options: props.categories.map(c => ({ value: c.id, label: c.name })),
        multiple: false,
        placeholder: 'Select Category',
        label: 'Category',
    },
    {
        name: 'is_active',
        type: 'select',
        options: [{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }],
        multiple: false,
        placeholder: 'Select Status',
        label: 'Status',
    },
];
</script>

<template>
    <Head title="Catalog - Goods" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Catalog: Goods</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="items"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'catalog.goods.create' }"
                        :editRoute="{ name: 'catalog.goods.edit' }"
                        :deleteRoute="{ name: 'catalog.goods.destroy' }"
                        :viewRoute="{ name: 'catalog.goods.show' }"
                        :indexRoute="{ name: 'catalog.goods.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="catalog.goods.index"
                        searchPlaceholder="Search code or name..."
                        @delete="deleteItem"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    </template>


