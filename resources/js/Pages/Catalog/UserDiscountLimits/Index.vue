<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    limits: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    users: Array,
    products: Array,
    categories: Array,
});

const currentSort = ref({ key: props.sort || 'id', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'user.name', label: 'Pengguna' },
    { key: 'scope', label: 'Cakupan' },
    { key: 'max_discount_percent', label: 'Maks Diskon' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    is_active: (value) => value ? 'Aktif' : 'Nonaktif',
};

const columnRenderers = {
    'user.name': (value, row) => row.user?.name ?? '-',
    scope: (value, row) => {
        if (row.product) return `Produk: ${row.product.name}`;
        if (row.product_category) return `Kategori: ${row.product_category.name}`;
        return 'Global';
    },
    max_discount_percent: (value) => formatNumber(value) + '%',
};

const customFilters = computed(() => [
    {
        name: 'user_global_id',
        type: 'select',
        options: props.users.map(u => ({ value: u.global_id, label: u.name })),
        multiple: true,
        placeholder: 'Pilih pengguna',
        label: 'Pengguna'
    },
    {
        name: 'product_category_id',
        type: 'select',
        options: props.categories.map(c => ({ value: c.id, label: c.name })),
        multiple: true,
        placeholder: 'Pilih kategori',
        label: 'Kategori'
    },
    {
        name: 'is_active',
        type: 'select',
        options: [
            { value: '1', label: 'Aktif' },
            { value: '0', label: 'Nonaktif' },
        ],
        multiple: false,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const sortableColumns = ['max_discount_percent', 'id'];
const defaultSort = { key: 'id', order: 'desc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('catalog.user-discount-limits.destroy', id), {
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

    router.delete(route('catalog.user-discount-limits.bulk-delete'), {
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
    router.get(route('catalog.user-discount-limits.index'), {
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
    router.get(route('catalog.user-discount-limits.index'), {
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
    <Head title="Batas Diskon Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Batas Diskon Pengguna</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="limits"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'catalog.user-discount-limits.create' }"
                        :editRoute="{ name: 'catalog.user-discount-limits.edit' }"
                        :deleteRoute="{ name: 'catalog.user-discount-limits.destroy' }"
                        :indexRoute="{ name: 'catalog.user-discount-limits.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="catalog.user-discount-limits.index"
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
