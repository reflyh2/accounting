<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    branchGroups: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Perusahaan', route: 'companies.index', active: false },
    { label: 'Kelompok Cabang', route: 'branch-groups.index', active: true },
    { label: 'Cabang', route: 'branches.index', active: false },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Kelompok Cabang' },
    { key: 'company.name', label: 'Perusahaan' }, // Add this line
    { key: 'branches_count', label: 'Jumlah Cabang' },
    { key: 'actions', label: '' }
];

const customFilters = [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map(company => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan'
    }
];

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'branches_count'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteBranchGroup(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('branch-groups.destroy', id), {
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

    router.delete(route('branch-groups.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
            ids: ids,
        },
    });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('branch-groups.index'), {
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
    <Head title="Pengaturan Kelompok Cabang" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Kelompok Cabang</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white overflow-hidden shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="branchGroups"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'branch-groups.create' }"
                        :editRoute="{ name: 'branch-groups.edit' }"
                        :deleteRoute="{ name: 'branch-groups.destroy' }"
                        :viewRoute="{ name: 'branch-groups.show' }"
                        :indexRoute="{ name: 'branch-groups.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="branch-groups"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="branch-groups.index"
                        @delete="deleteBranchGroup"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>