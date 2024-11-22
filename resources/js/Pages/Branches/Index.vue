<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    branches: Object,
    branchGroups: Array,
    companies: Array,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Perusahaan', route: 'companies.index', active: false },
    { label: 'Kelompok Cabang', route: 'branch-groups.index', active: false },
    { label: 'Cabang', route: 'branches.index', active: true },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Cabang' },
    { key: 'address', label: 'Alamat' },
    { key: 'branch_group.name', label: 'Kelompok Cabang' },
    { key: 'branch_group.company.name', label: 'Perusahaan' },
    { key: 'actions', label: '' }
];

const branchGroupOptions = computed(() => 
    props.branchGroups.map(branchGroup => ({ value: branchGroup.id, label: branchGroup.name }))
);

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map(company => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan'
    },
    {
        name: 'branch_group_id',
        type: 'select',
        options: branchGroupOptions.value,
        multiple: true,
        placeholder: 'Pilih kelompok cabang',
        label: 'Kelompok Cabang'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'address', 'branch_group.name', 'branch_group.company.name'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteBranch(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('branches.destroy', id), {
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

    router.delete(route('branches.bulk-delete'), {
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
    router.get(route('branches.index'), {
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
    router.get(route('branches.index'), {
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
    <Head title="Pengaturan Cabang" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Cabang</h2>
        </template>

        <div class="min-w-min md:min-w-max mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="branches"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'branches.create' }"
                        :editRoute="{ name: 'branches.edit' }"
                        :deleteRoute="{ name: 'branches.destroy' }"
                        :viewRoute="{ name: 'branches.show' }"
                        :indexRoute="{ name: 'branches.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="branches"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="branches.index"
                        @delete="deleteBranch"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>