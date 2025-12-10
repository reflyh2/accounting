<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TaxConfigTabs from '@/Tabs/TaxConfigTabs.vue';

const props = defineProps({
    jurisdictions: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    levels: Array,
    countryCodes: Array,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const levelLabels = {
    country: 'Negara',
    state: 'Provinsi',
    city: 'Kota',
    district: 'Kecamatan',
    village: 'Desa'
};

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'country_code', label: 'Negara' },
    { key: 'level', label: 'Level' },
    { key: 'tax_authority', label: 'Otoritas Pajak' },
    { key: 'parent.name', label: 'Induk' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    level: (value) => levelLabels[value],
};

const customFilters = computed(() => [
    {
        name: 'level',
        type: 'select',
        options: props.levels.map(level => ({ value: level, label: level })),
        multiple: true,
        placeholder: 'Pilih level',
        label: 'Level'
    },
    {
        name: 'country_code',
        type: 'select',
        options: props.countryCodes.map(code => ({ value: code, label: code })),
        multiple: true,
        placeholder: 'Pilih negara',
        label: 'Kode Negara'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['code', 'name', 'country_code', 'level'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('tax-jurisdictions.destroy', id), {
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

    router.delete(route('tax-jurisdictions.bulk-delete'), {
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
    router.get(route('tax-jurisdictions.index'), {
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
    router.get(route('tax-jurisdictions.index'), {
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
    <Head title="Yurisdiksi Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Yurisdiksi Pajak</h2>
        </template>

        <div class="mx-auto">
            <TaxConfigTabs activeTab="tax-jurisdictions.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="jurisdictions"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'tax-jurisdictions.create' }"
                        :editRoute="{ name: 'tax-jurisdictions.edit' }"
                        :deleteRoute="{ name: 'tax-jurisdictions.destroy' }"
                        :viewRoute="{ name: 'tax-jurisdictions.show' }"
                        :indexRoute="{ name: 'tax-jurisdictions.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="tax-jurisdictions"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="tax-jurisdictions.index"
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
