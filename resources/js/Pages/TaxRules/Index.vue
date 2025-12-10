<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TaxConfigTabs from '@/Tabs/TaxConfigTabs.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    rules: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    categories: Array,
    jurisdictions: Array,
    components: Array,
});

const currentSort = ref({ key: props.sort || 'priority', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'tax_category.name', label: 'Kategori' },
    { key: 'jurisdiction.name', label: 'Yurisdiksi' },
    { key: 'component.name', label: 'Komponen' },
    { key: 'rate_value', label: 'Tarif' },
    { key: 'tax_inclusive', label: 'Inklusif'},
    { key: 'effective_from', label: 'Berlaku Dari' },
    { key: 'priority', label: 'Prioritas' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    tax_inclusive: (value) => value ? 'Ya' : 'Tidak',
    effective_from: (value) => new Date(value).toLocaleDateString('ID-id'),
};

const columnRenderers = {
    rate_value: (value, row) => row.rate_type === 'percent' ? formatNumber(value) + '%' : formatNumber(value),
};

const customFilters = computed(() => [
    {
        name: 'tax_category_id',
        type: 'select',
        options: props.categories.map(c => ({ value: c.id, label: c.name })),
        multiple: true,
        placeholder: 'Pilih kategori',
        label: 'Kategori'
    },
    {
        name: 'tax_jurisdiction_id',
        type: 'select',
        options: props.jurisdictions.map(j => ({ value: j.id, label: j.name })),
        multiple: true,
        placeholder: 'Pilih yurisdiksi',
        label: 'Yurisdiksi'
    },
    {
        name: 'tax_component_id',
        type: 'select',
        options: props.components.map(c => ({ value: c.id, label: c.name })),
        multiple: true,
        placeholder: 'Pilih komponen',
        label: 'Komponen'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['priority', 'rate_value', 'effective_from'];
const defaultSort = { key: 'priority', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('tax-rules.destroy', id), {
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

    router.delete(route('tax-rules.bulk-delete'), {
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
    router.get(route('tax-rules.index'), {
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
    router.get(route('tax-rules.index'), {
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
    <Head title="Aturan Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Aturan Pajak</h2>
        </template>

        <div class="mx-auto">
            <TaxConfigTabs activeTab="tax-rules.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="rules"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'tax-rules.create' }"
                        :editRoute="{ name: 'tax-rules.edit' }"
                        :deleteRoute="{ name: 'tax-rules.destroy' }"
                        :viewRoute="{ name: 'tax-rules.show' }"
                        :indexRoute="{ name: 'tax-rules.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="tax-rules"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="tax-rules.index"
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
