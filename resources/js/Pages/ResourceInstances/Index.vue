<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    resourceInstances: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    resourcePools: Array,
    statusOptions: Array,
});

const currentSort = ref({ key: props.sort || 'code', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'pool.name', label: 'Pool' },
    { key: 'pool.product.name', label: 'Produk' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnRenderers = {
    status: (value) => {
        const colors = {
            active: 'text-emerald-600',
            maintenance: 'text-amber-600',
            retired: 'text-gray-500',
        };
        const labels = {
            active: 'Aktif',
            maintenance: 'Maintenance',
            retired: 'Tidak Aktif',
        };
        return `<span class="${colors[value] || 'text-gray-600'}">${labels[value] || value}</span>`;
    },
};

const poolOptions = computed(() =>
    props.resourcePools.map((pool) => ({
        value: pool.id,
        label: `${pool.name} (${pool.product_name || ''})`,
    }))
);

const customFilters = computed(() => [
    {
        name: 'resource_pool_id',
        type: 'select',
        options: poolOptions.value,
        multiple: true,
        placeholder: 'Pilih Pool',
        label: 'Resource Pool',
    },
    {
        name: 'status',
        type: 'select',
        options: props.statusOptions,
        placeholder: 'Status',
        label: 'Status',
    },
]);

const sortableColumns = ['code', 'status'];
const defaultSort = { key: 'code', order: 'asc' };

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('resource-instances.index'), {
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('resource-instances.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function deleteResourceInstance(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('resource-instances.destroy', id), {
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
    <Head title="Resource Instance" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Resource Instance</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="resourceInstances"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'resource-instances.create' }"
                        :editRoute="{ name: 'resource-instances.edit' }"
                        :deleteRoute="{ name: 'resource-instances.destroy' }"
                        :indexRoute="{ name: 'resource-instances.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="resource-instances.index"
                        :enableViewAction="false"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deleteResourceInstance"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
