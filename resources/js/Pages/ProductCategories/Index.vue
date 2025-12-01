<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    categories: Object,
    filters: Object,
    perPage: [String, Number],
    sortColumn: String,
    sortOrder: String,
    companies: Array,
    attributeSets: Array,
});

const currentSort = ref({
    key: props.sortColumn || 'sort_order',
    order: props.sortOrder || 'asc',
});

const currentFilters = ref(props.filters || {});

const companyOptions = computed(() => props.companies?.map(company => ({
    value: company.id,
    label: company.name,
})) ?? []);

const attributeSetOptions = computed(() => props.attributeSets?.map(set => ({
    value: set.id,
    label: set.name,
})) ?? []);

const customFilters = computed(() => ([
    {
        name: 'company_id',
        type: 'select',
        label: 'Perusahaan',
        options: companyOptions.value,
        placeholder: 'Semua perusahaan',
    },
    {
        name: 'attribute_set_id',
        type: 'select',
        label: 'Set Atribut',
        options: attributeSetOptions.value,
        placeholder: 'Semua set atribut',
    },
]));

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama Kategori' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'attribute_set.name', label: 'Set Atribut' },
    { key: 'parent.name', label: 'Kategori Induk' },
    { key: 'sort_order', label: 'Urutan' },
    { key: 'path', label: 'Path' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    'company.name': (value) => value || '-',
    'attribute_set.name': (value) => value || '-',
    'parent.name': (value) => value || '-',
    sort_order: (value) => value ?? '-',
    path: (value) => value || '-',
};

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' },
];

const sortableColumns = ['code', 'name', 'sort_order'];
const defaultSort = { key: 'sort_order', order: 'asc' };

function deleteCategory(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('catalog.product-categories.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery,
        },
    });
}

function handleBulkDelete(ids) {
    if (!ids?.length) return;

    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('catalog.product-categories.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery,
            ids,
        },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('catalog.product-categories.index'), {
        ...route().params,
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: currentFilters.value.per_page || props.perPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('catalog.product-categories.index'), {
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
    <Head title="Kategori Produk" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Kategori Produk</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="categories"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :createRoute="{ name: 'catalog.product-categories.create' }"
                        :editRoute="{ name: 'catalog.product-categories.edit' }"
                        :deleteRoute="{ name: 'catalog.product-categories.destroy' }"
                        :viewRoute="{ name: 'catalog.product-categories.show' }"
                        :indexRoute="{ name: 'catalog.product-categories.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="catalog.product-categories"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="catalog.product-categories.index"
                        :customFilters="customFilters"
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

