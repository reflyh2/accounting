<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    users: Object,
    roles: Array,
    branches: Array,
    companies: Array,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Hak Akses', route: 'roles.index', active: false },
    { label: 'Pengguna', route: 'users.index', active: true },
];

const tableHeaders = [
    { key: 'name', label: 'Nama' },
    { key: 'email', label: 'Email' },
    { key: 'roles', label: 'Peran' },
    { key: 'branches', label: 'Cabang' },
    { key: 'actions', label: '' }
];

const customFilters = [
    {
        name: 'role_id',
        type: 'select',
        options: props.roles.map(role => ({ value: role.id, label: role.name })),
        multiple: true,
        placeholder: 'Pilih hak akses',
        label: 'Hak Akses'  
    },
    {
        name: 'branch_id',
        type: 'select',
        options: props.branches.map(branch => ({ value: branch.id, label: branch.name })),
        multiple: true,
        placeholder: 'Pilih cabang',
        label: 'Cabang'
    }
];

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'email'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteUser(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('users.destroy', id), {
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

    router.delete(route('users.bulk-delete'), {
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
    router.get(route('users.index'), {
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
    router.get(route('users.index'), {
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
    <Head title="Pengaturan Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Pengguna</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="users"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'users.create' }"
                        :editRoute="{ name: 'users.edit' }"
                        :deleteRoute="{ name: 'users.destroy' }"
                        :viewRoute="{ name: 'users.show' }"
                        :indexRoute="{ name: 'users.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="users"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="users.index"
                        @delete="deleteUser"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>