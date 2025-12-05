<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    partners: Object,
    filters: Object,
    companies: Array,
    availableRoles: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'phone', label: 'Telepon' },
    { key: 'email', label: 'Email' },
    { key: 'city', label: 'Kota' },
    { key: 'roles', label: 'Peran' },
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
        name: 'role',
        type: 'select',
        options: Object.entries(props.availableRoles).map(([key, value]) => ({ value: key, label: value })),
        multiple: true,
        placeholder: 'Pilih peran',
        label: 'Peran'
    },
    {
        name: 'status',
        type: 'select',
        options: [
            { value: 'active', label: 'Aktif' },
            { value: 'inactive', label: 'Tidak Aktif' }
        ],
        multiple: false,
        placeholder: 'Pilih status',
        label: 'Status'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    roles: (role) => {
        if (!role || !Array.isArray(role)) {
            return '';
        }
        return role.map(role => props.availableRoles[role.role] || role.role).join(', ');
    },
    status: (value) => value === 'active' ? 'Aktif' : 'Tidak Aktif',
};

const sortableColumns = ['code', 'name', 'phone', 'email', 'city', 'status'];
const defaultSort = { key: 'name', order: 'asc' };

function deletePartner(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('partners.destroy', id), {
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

    router.delete(route('partners.bulk-delete'), {
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
    router.get(route('partners.index'), {
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
    router.get(route('partners.index'), {
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
    <Head title="Partner Bisnis" />

    <AuthenticatedLayout>
        <template #header>
            
            <h2>Partner Bisnis</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="partners"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'partners.create' }"
                        :editRoute="{ name: 'partners.edit' }"
                        :deleteRoute="{ name: 'partners.destroy' }"
                        :viewRoute="{ name: 'partners.show' }"
                        :indexRoute="{ name: 'partners.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="partners"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="partners.index"
                        @delete="deletePartner"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 