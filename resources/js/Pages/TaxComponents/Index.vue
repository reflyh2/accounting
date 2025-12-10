<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TaxConfigTabs from '@/Tabs/TaxConfigTabs.vue';

const props = defineProps({
    components: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    jurisdictions: Array,
    kinds: Array,
    cascadeModes: Array,
    deductibleModes: Array,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'jurisdiction.name', label: 'Yurisdiksi' },
    { key: 'kind', label: 'Jenis' },
    { key: 'cascade_mode', label: 'Pajak Berganda' },
    { key: 'deductible_mode', label: 'Pengkreditan' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    kind: (value) => props.kinds[value],
    cascade_mode: (value) => props.cascadeModes[value],
    deductible_mode: (value) => props.deductibleModes[value],
};


const customFilters = computed(() => [
    {
        name: 'kind',
        type: 'select',
        options: props.kinds.map(kind => ({ value: kind, label: kind })),
        multiple: true,
        placeholder: 'Pilih jenis',
        label: 'Jenis'
    },
    {
        name: 'tax_jurisdiction_id',
        type: 'select',
        options: props.jurisdictions.map(j => ({ value: j.id, label: j.name })),
        multiple: true,
        placeholder: 'Pilih yurisdiksi',
        label: 'Yurisdiksi'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['code', 'name', 'kind'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('tax-components.destroy', id), {
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

    router.delete(route('tax-components.bulk-delete'), {
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
    router.get(route('tax-components.index'), {
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
    router.get(route('tax-components.index'), {
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
    <Head title="Komponen Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Komponen Pajak</h2>
        </template>

        <div class="mx-auto">
            <TaxConfigTabs activeTab="tax-components.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="components"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'tax-components.create' }"
                        :editRoute="{ name: 'tax-components.edit' }"
                        :deleteRoute="{ name: 'tax-components.destroy' }"
                        :viewRoute="{ name: 'tax-components.show' }"
                        :indexRoute="{ name: 'tax-components.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="tax-components"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="tax-components.index"
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
