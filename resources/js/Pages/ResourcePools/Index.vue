<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    resourcePools: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    branches: Array,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'name', label: 'Nama Pool' },
    { key: 'product.name', label: 'Produk' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'default_capacity', label: 'Kapasitas' },
    { key: 'instances_count', label: 'Instance' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnRenderers = {
    is_active: (value) => value
        ? '<span class="text-emerald-600 font-medium">Aktif</span>'
        : '<span class="text-gray-500">Nonaktif</span>',
};

const branchOptions = computed(() =>
    props.branches.map((branch) => ({
        value: branch.id,
        label: branch.name,
    }))
);

const customFilters = computed(() => [
    {
        name: 'branch_id',
        type: 'select',
        options: branchOptions.value,
        multiple: true,
        placeholder: 'Pilih Cabang',
        label: 'Cabang',
    },
    {
        name: 'is_active',
        type: 'select',
        options: [
            { value: 1, label: 'Aktif' },
            { value: 0, label: 'Nonaktif' },
        ],
        placeholder: 'Status',
        label: 'Status',
    },
]);

const sortableColumns = ['name', 'default_capacity', 'is_active'];
const defaultSort = { key: 'name', order: 'asc' };

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('resource-pools.index'), {
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('resource-pools.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function deleteResourcePool(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('resource-pools.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
        },
    });
}
</script>

<template>
    <Head title="Resource Pool" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Resource Pool</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="resourcePools"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'resource-pools.create' }"
                        :editRoute="{ name: 'resource-pools.edit' }"
                        :viewRoute="{ name: 'resource-pools.show' }"
                        :deleteRoute="{ name: 'resource-pools.destroy' }"
                        :indexRoute="{ name: 'resource-pools.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="resource-pools.index"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deleteResourcePool"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
