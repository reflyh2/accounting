<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { Cog8ToothIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    companies: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = [
    { label: 'Perusahaan', route: 'companies.index', active: true },
    { label: 'Kelompok Cabang', route: 'branch-groups.index', active: false },
    { label: 'Cabang', route: 'branches.index', active: false },
];

const tableHeaders = [
    { key: 'name', label: 'Nama Perusahaan' },
    { key: 'legal_name', label: 'Nama Resmi' },
    { key: 'address', label: 'Alamat' },
    { key: 'phone', label: 'Telepon' },
    { key: 'branches_count', label: 'Jumlah Cabang' },
    { key: 'actions', label: '' }
];

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'legal_name', 'tax_id', 'address', 'city', 'phone', 'branches_count'];
const defaultSort = { key: 'name', order: 'asc' };

const columnRenderers = {
    name: (value, item) => {
        const hasNullAccounts = !item.default_receivable_account_id ||
            !item.default_payable_account_id ||
            !item.default_revenue_account_id ||
            !item.default_cogs_account_id ||
            !item.default_retained_earnings_account_id;

        if (hasNullAccounts) {
            return `
                <div class="flex items-center">
                    <span>${value}</span>
                    <span class="ml-2 text-yellow-500" title="Pengaturan akun standar perusahaan belum diset!">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </div>`;
        }
        return value;
    }
};

function deleteCompany(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('companies.destroy', id), {
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

    router.delete(route('companies.bulk-delete'), {
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
    router.get(route('companies.index'), {
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
    router.get(route('companies.index'), {
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
    <Head title="Pengaturan Perusahaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Perusahaan</h2>
        </template>

        <div class="min-w-min md:min-w-max mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="companies"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'companies.create' }"
                        :editRoute="{ name: 'companies.edit' }"
                        :deleteRoute="{ name: 'companies.destroy' }"
                        :viewRoute="{ name: 'companies.show' }"
                        :indexRoute="{ name: 'companies.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="companies"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :columnRenderers="columnRenderers"
                        :perPage="perPage"
                        routeName="companies.index"
                        @delete="deleteCompany"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <Link :href="route('companies.default-accounts.edit', item.id)" class="mr-3" title="Pengaturan Akun Standar">
                                <button type="button" title="Pengaturan Akun Standar" class="inline-flex items-center justify-center align-middle h-4 w-4 md:ml-2 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50">
                                    <Cog8ToothIcon class="h-4 w-4" />
                                </button>
                            </Link>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>