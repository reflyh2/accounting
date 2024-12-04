<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import TabLinks from '@/Components/TabLinks.vue';
import { getStatusClass } from '@/constants/businessRelationStatus';

const props = defineProps({
    customers: Object,
    companies: Array,
    filters: Object,
    perPage: [String, Number],
    sortColumn: String,
    sortOrder: String,
    statuses: Object,
});

const currentSort = ref({ key: props.sortColumn || 'name', order: props.sortOrder || 'asc' });
const currentFilters = ref(props.filters || {});

const tabs = ref([
    { label: 'Pemasok', route: 'suppliers.index', active: false },
    { label: 'Pelanggan', route: 'customers.index', active: true },
    { label: 'Anggota', route: 'members.index', active: false },
    { label: 'Partner', route: 'partners.index', active: false },
    { label: 'Karyawan', route: 'employees.index', active: false },
]);

const tableHeaders = [
    { key: 'name', label: 'Nama' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Telepon' },
    { key: 'companies', label: 'Perusahaan' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

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
        name: 'status',
        type: 'select',
        options: Object.entries(props.statuses).map(([value, label]) => ({ value, label })),
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const sortableColumns = ['name', 'email', 'phone', 'company.name', 'status'];
const defaultSort = { key: 'name', order: 'asc' };

const columnFormatters = {
    status: (value) => `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(value)}">${props.statuses[value]}</span>`,
};

function deleteCustomer(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('customers.destroy', id), {
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

    router.delete(route('customers.bulk-delete'), {
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
    router.get(route('customers.index'), {
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
    router.get(route('customers.index'), {
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
    <Head title="Pengaturan Pelanggan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Pelanggan</h2>
        </template>

        <div class="min-w-min md:min-w-max mx-auto">
            <TabLinks :tabs="tabs" />
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="customers"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :createRoute="{ name: 'customers.create' }"
                        :editRoute="{ name: 'customers.edit' }"
                        :deleteRoute="{ name: 'customers.destroy' }"
                        :viewRoute="{ name: 'customers.show' }"
                        :indexRoute="{ name: 'customers.index' }"
                        :customFilters="customFilters"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="customers"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="customers.index"
                        :columnFormatters="columnFormatters"
                        @delete="deleteCustomer"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #cell-status="{ item }">
                            <span 
                                class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-green-100 text-green-800': item.status === 'active',
                                    'bg-gray-100 text-gray-800': item.status === 'inactive',
                                    'bg-red-100 text-red-800': item.status === 'suspended'
                                }"
                            >
                                {{ statuses[item.status] }}
                            </span>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 