<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { LockClosedIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    roles: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Hak Akses', route: 'roles.index', active: true },
    { label: 'Pengguna', route: 'users.index', active: false },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Hak Akses' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'access_level_label', label: 'Tingkat Akses' },
    { key: 'actions', label: '' }
];

const customFilters = [
    {
        name: 'access_level',
        type: 'select',
        options: [
            { value: 'own', label: 'Data Sendiri' },
            { value: 'branch', label: 'Cabang' },
            { value: 'branch_group', label: 'Kelompok Cabang' },
            { value: 'company', label: 'Perusahaan' }
        ],
        multiple: true,
        placeholder: 'Pilih tingkat akses',
        label: 'Tingkat Akses'
    }
];

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'description'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteRole(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('roles.destroy', id), {
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

    router.delete(route('roles.bulk-delete'), {
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
    router.get(route('roles.index'), {
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
    router.get(route('roles.index'), {
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
    <Head title="Pengaturan Hak Akses" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Hak Akses</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="roles"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'roles.create' }"
                        :editRoute="{ name: 'roles.edit' }"
                        :deleteRoute="{ name: 'roles.destroy' }"
                        :viewRoute="{ name: 'roles.show' }"
                        :indexRoute="{ name: 'roles.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="roles"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="roles.index"
                        @delete="deleteRole"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <Link :href="route('roles.permissions', item.id)" class="mr-3">
                                <button type="button" title="Set Hak Akses" class="inline-flex items-center justify-center align-middle h-4 w-4 md:ml-2 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50">
                                    <LockClosedIcon class="h-4 w-4" />
                                </button>
                            </Link>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>