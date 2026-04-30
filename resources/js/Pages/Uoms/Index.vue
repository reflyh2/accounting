<script setup>
import { ref, computed } from 'vue';
import { router, Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import UomTabs from '@/Tabs/UomTabs.vue';

const props = defineProps({
    uoms: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    kinds: Array,
});

const currentSort = ref({ key: props.sort || 'code', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'kind', label: 'Jenis' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    kind: (value) => {
        const k = props.kinds?.find((x) => x.value === value);
        return k ? k.label : value;
    },
};

const customFilters = computed(() => [
    {
        name: 'kind',
        type: 'select',
        options: props.kinds || [],
        multiple: true,
        placeholder: 'Pilih jenis',
        label: 'Jenis',
    },
]);

const sortableColumns = ['code', 'name', 'kind'];
const defaultSort = { key: 'code', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('uoms.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery },
    });
}

function handleBulkDelete(ids) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('uoms.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: { preserveState: true, currentQuery, ids },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('uoms.index'), {
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('uoms.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Satuan (UoM)" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Satuan (UoM)</h2>
        </template>

        <div class="mx-auto">
            <UomTabs activeTab="uoms.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="uoms"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'uoms.create' }"
                        :editRoute="{ name: 'uoms.edit' }"
                        :deleteRoute="{ name: 'uoms.destroy' }"
                        :indexRoute="{ name: 'uoms.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="uoms.index"
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
