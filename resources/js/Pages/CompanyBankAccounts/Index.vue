
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    bankAccounts: Object,
    filters: Object,
    companies: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'bank_name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'bank_name', label: 'Bank' },
    { key: 'account_number', label: 'No. Rekening' },
    { key: 'account_holder_name', label: 'Nama Pemilik' },
    { key: 'account', label: 'Akun GL' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' }
];

const columnFormatters = {
    account: (row) => row.account ? `${row.account.code} - ${row.account.name}` : '-',
    is_active: (row) => row.is_active ? 'Aktif' : 'Nonaktif',
};

const columnRenderers = {
    is_active: (value) => value 
        ? `<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Aktif</span>` 
        : `<span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Nonaktif</span>`,
};

const filterOptions = [
    { name: 'company_id', label: 'Perusahaan', options: props.companies },
    { name: 'is_active', label: 'Status', options: [
        { value: 1, label: 'Aktif' },
        { value: 0, label: 'Nonaktif' },
    ]},
];

const sortableColumns = ['bank_name', 'account_number', 'account_holder_name'];
const defaultSort = { key: 'bank_name', order: 'asc' };

function deleteBankAccount(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('company-bank-accounts.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery
        },
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('company-bank-accounts.index'), {
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
    router.get(route('company-bank-accounts.index'), {
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
    <Head title="Rekening Bank Perusahaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Rekening Bank Perusahaan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="bankAccounts"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :filterOptions="filterOptions"
                        :createRoute="{ name: 'company-bank-accounts.create' }"
                        :editRoute="{ name: 'company-bank-accounts.edit' }"
                        :deleteRoute="{ name: 'company-bank-accounts.destroy' }"
                        :viewRoute="{ name: 'company-bank-accounts.show' }"
                        :indexRoute="{ name: 'company-bank-accounts.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="company-bank-accounts.index"
                        @delete="deleteBankAccount"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
