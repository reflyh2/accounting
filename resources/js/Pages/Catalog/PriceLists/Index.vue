<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import PriceConfigTabs from '@/Tabs/PriceConfigTabs.vue';

const props = defineProps({
    priceLists: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    currencies: Array,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'channel', label: 'Kanal' },
    { key: 'validity', label: 'Periode Berlaku' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    is_active: (value) => value ? 'Aktif' : 'Nonaktif',
};

const columnRenderers = {
    'company.name': (value, row) => row.company?.name ?? '-',
    'currency.code': (value, row) => row.currency?.code ?? '-',
    channel: (value) => value ?? '-',
    validity: (value, row) => {
        const from = row.valid_from ? new Date(row.valid_from).toLocaleDateString('id-ID') : '-';
        const to = row.valid_to ? new Date(row.valid_to).toLocaleDateString('id-ID') : '-';
        return `${from} → ${to}`;
    },
};

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map(c => ({ value: c.id, label: c.name })),
        multiple: true,
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan'
    },
    {
        name: 'currency_id',
        type: 'select',
        options: props.currencies.map(c => ({ value: c.id, label: `${c.code} - ${c.name}` })),
        multiple: true,
        placeholder: 'Pilih mata uang',
        label: 'Mata Uang'
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

const sortableColumns = ['code', 'name'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('catalog.price-lists.destroy', id), {
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

    router.delete(route('catalog.price-lists.bulk-delete'), {
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
    router.get(route('catalog.price-lists.index'), {
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
    router.get(route('catalog.price-lists.index'), {
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
    <Head title="Kelompok Harga" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Kelompok Harga</h2>
        </template>

        <div class="mx-auto">
            <PriceConfigTabs activeTab="catalog.price-lists.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="priceLists"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'catalog.price-lists.create' }"
                        :editRoute="{ name: 'catalog.price-lists.edit' }"
                        :deleteRoute="{ name: 'catalog.price-lists.destroy' }"
                        :viewRoute="{ name: 'catalog.price-lists.show' }"
                        :indexRoute="{ name: 'catalog.price-lists.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="catalog.price-lists.index"
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
