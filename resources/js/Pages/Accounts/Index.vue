<script setup>
import { ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { multiply } from 'lodash';

const props = defineProps({
    accounts: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
});

const currentSort = ref({ key: props.sort || 'code', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const accountTypes = [
   { value: 'kas_bank', label: 'Kas & Bank' },
   { value: 'piutang_usaha', label: 'Piutang Usaha' },
   { value: 'persediaan', label: 'Persediaan' },
   { value: 'aset_lancar_lainnya', label: 'Aset Lancar Lainnya' },
   { value: 'aset_tetap', label: 'Aset Tetap' },
   { value: 'akumulasi_penyusutan', label: 'Akumulasi Penyusutan' },
   { value: 'aset_lainnya', label: 'Aset Lainnya' },
   { value: 'hutang_usaha', label: 'Hutang Usaha' },
   { value: 'hutang_usaha_lainnya', label: 'Hutang Usaha Lainnya' },
   { value: 'liabilitas_jangka_pendek', label: 'Liabilitas Jangka Pendek' },
   { value: 'liabilitas_jangka_panjang', label: 'Liabilitas Jangka Panjang' },
   { value: 'modal', label: 'Modal' },
   { value: 'pendapatan', label: 'Pendapatan' },
   { value: 'beban_pokok_penjualan', label: 'Beban Pokok Penjualan' },
   { value: 'beban', label: 'Beban' },
   { value: 'beban_penyusutan', label: 'Beban Penyusutan' },
   { value: 'beban_amortisasi', label: 'Beban Amortisasi' },
   { value: 'beban_lainnya', label: 'Beban Lainnya' },
   { value: 'pendapatan_lainnya', label: 'Pendapatan Lainnya' },
];

const tableHeaders = [
    { key: 'code', label: 'Kode Akun' },
    { key: 'name', label: 'Nama Akun' },
    { key: 'type', label: 'Tipe' },
    { key: 'actions', label: '' }
];

const customFilters = [
    {
        name: 'type',
        type: 'select',
        options: accountTypes,
        multiple: true,
        placeholder: 'Pilih tipe akun',
        label: 'Tipe Akun'
    },
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map(company => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan'
    }
];

const perPageOptions = [10, 25, 50, 100, 500, 1000];

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['code', 'name', 'type', 'parent.name'];
const defaultSort = { key: 'code', order: 'asc' };

const columnFormatters = {
    type: (value) => accountTypes.find(type => type.value === value)?.label || value,
};

const columnRenderers = {
    code: (value) => `<span class="ml-${props.accounts.data.find(account => account.code === value)?.level * 4}">${value}</span>`,
    name: (value) => `<span class="ml-${props.accounts.data.find(account => account.name === value)?.level * 4}">${value}</span>`,
};

function deleteAccount(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('accounts.destroy', id), {
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

    router.delete(route('accounts.bulk-delete'), {
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
    router.get(route('accounts.index'), {
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
    router.get(route('accounts.index'), {
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
    <Head title="Daftar Akun" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Akun</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="accounts"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :createRoute="{ name: 'accounts.create' }"
                        :editRoute="{ name: 'accounts.edit' }"
                        :deleteRoute="{ name: 'accounts.destroy' }"
                        :viewRoute="{ name: 'accounts.show' }"
                        :indexRoute="{ name: 'accounts.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="accounts"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        :perPageOptions="perPageOptions"
                        routeName="accounts.index"
                        @delete="deleteAccount"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>