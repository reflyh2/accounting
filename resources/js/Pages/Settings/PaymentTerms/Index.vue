<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    paymentTerms: Object,
    filters: Object,
    perPage: [String, Number],
    companies: Array,
});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'days', label: 'Hari' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    is_active: (value) => value ? 'Aktif' : 'Non-aktif',
};

const currentSort = ref({ key: 'name', order: 'asc' });
const sortableColumns = ['code', 'name', 'days'];
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
    router.get(route('settings.payment-terms.index'), {
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
    router.get(route('settings.payment-terms.index'), {
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

function deletePaymentTerm(id) {
    router.delete(route('settings.payment-terms.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Payment Terms" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Payment Terms</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="paymentTerms"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'settings.payment-terms.create' }"
                        :editRoute="{ name: 'settings.payment-terms.edit' }"
                        :viewRoute="{ name: 'settings.payment-terms.show' }"
                        :deleteRoute="{ name: 'settings.payment-terms.destroy' }"
                        :indexRoute="{ name: 'settings.payment-terms.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="settings.payment-terms.index"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deletePaymentTerm"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
