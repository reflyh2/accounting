<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    costItems: Object,
    filters: Object,
    perPage: [String, Number],
    companies: Array,
});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'debit_account.code', label: 'Akun Debit' },
    { key: 'credit_account.code', label: 'Akun Kredit' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    is_active: (value) => value ? 'Aktif' : 'Non-aktif',
};

const currentSort = ref({ key: 'name', order: 'asc' });
const sortableColumns = ['code', 'name'];
const defaultSort = { key: 'name', order: 'asc' };

const currentFilters = ref(props.filters || {});

const customFilters = computed(() => [
    {
        name: 'company_id',
        type: 'select',
        options: props.companies.map((company) => ({ value: company.id, label: company.name })),
        multiple: true,
        placeholder: 'Pilih Perusahaan',
        label: 'Perusahaan',
    },
    {
        name: 'is_active',
        type: 'select',
        options: [
            { value: 'true', label: 'Aktif' },
            { value: 'false', label: 'Non-aktif' },
        ],
        multiple: false,
        placeholder: 'Status',
        label: 'Status',
    },
]);

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('costing.cost-items.index'), {
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
    router.get(route('costing.cost-items.index'), {
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

function deleteCostItem(id) {
    router.delete(route('costing.cost-items.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Cost Items" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Cost Items</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="costItems"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'costing.cost-items.create' }"
                        :editRoute="{ name: 'costing.cost-items.edit' }"
                        :viewRoute="{ name: 'costing.cost-items.show' }"
                        :deleteRoute="{ name: 'costing.cost-items.destroy' }"
                        :indexRoute="{ name: 'costing.cost-items.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="costing.cost-items.index"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deleteCostItem"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
