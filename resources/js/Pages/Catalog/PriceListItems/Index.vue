<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import PriceConfigTabs from '@/Tabs/PriceConfigTabs.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    items: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    priceLists: Array,
    products: Array,
});

const currentSort = ref({ key: props.sort || 'id', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'price_list.name', label: 'Daftar Harga' },
    { key: 'product.name', label: 'Produk' },
    { key: 'variant', label: 'Varian' },
    { key: 'uom.name', label: 'UOM' },
    { key: 'min_qty', label: 'Min Qty' },
    { key: 'price', label: 'Harga' },
    { key: 'tax_included', label: 'Termasuk Pajak' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    tax_included: (value) => value ? 'Ya' : 'Tidak',
};

const columnRenderers = {
    'price_list.name': (value, row) => row.price_list?.name ?? '-',
    'product.name': (value, row) => row.product?.name ?? '-',
    variant: (value, row) => row.product_variant?.sku ?? '-',
    'uom.name': (value, row) => row.uom?.name ?? '-',
    min_qty: (value) => formatNumber(value),
    price: (value) => formatNumber(value),
};

const customFilters = computed(() => [
    {
        name: 'price_list_id',
        type: 'select',
        options: props.priceLists.map(p => ({ value: p.id, label: `${p.name} (${p.code})` })),
        multiple: true,
        placeholder: 'Pilih daftar harga',
        label: 'Daftar Harga'
    },
    {
        name: 'product_id',
        type: 'select',
        options: props.products.map(p => ({ value: p.id, label: `${p.name} (${p.code})` })),
        multiple: true,
        placeholder: 'Pilih produk',
        label: 'Produk'
    }
]);

const sortableColumns = ['min_qty', 'price'];
const defaultSort = { key: 'id', order: 'desc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('catalog.price-list-items.destroy', id), {
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

    router.delete(route('catalog.price-list-items.bulk-delete'), {
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
    router.get(route('catalog.price-list-items.index'), {
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
    router.get(route('catalog.price-list-items.index'), {
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
    <Head title="Harga" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Harga</h2>
        </template>

        <div class="mx-auto">
            <PriceConfigTabs activeTab="catalog.price-list-items.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="items"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'catalog.price-list-items.create' }"
                        :editRoute="{ name: 'catalog.price-list-items.edit' }"
                        :deleteRoute="{ name: 'catalog.price-list-items.destroy' }"
                        :indexRoute="{ name: 'catalog.price-list-items.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="catalog.price-list-items.index"
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
