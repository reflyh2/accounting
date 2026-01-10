<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    costEntries: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    sourceTypeOptions: Object,
});

const tableHeaders = [
    { key: 'cost_date', label: 'Tanggal' },
    { key: 'source_type', label: 'Sumber' },
    { key: 'product.name', label: 'Produk' },
    { key: 'company.name', label: 'Perusahaan' },
    { key: 'description', label: 'Deskripsi' },
    { key: 'amount_base', label: 'Jumlah (Base)' },
    { key: 'allocated_amount', label: 'Teralokasi' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    cost_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    amount_base: (value) => formatNumber(value),
    allocated_amount: (value) => formatNumber(value),
    source_type: (value) => props.sourceTypeOptions?.[value] || value,
};

const currentSort = ref({ key: props.sort || 'cost_date', order: props.order || 'desc' });
const sortableColumns = ['cost_date', 'amount_base', 'allocated_amount', 'source_type'];
const defaultSort = { key: 'cost_date', order: 'desc' };

const currentFilters = ref(props.filters || {});

const sourceTypeFilterOptions = computed(() =>
    Object.entries(props.sourceTypeOptions || {}).map(([value, label]) => ({
        value,
        label,
    }))
);

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
        name: 'source_type',
        type: 'select',
        options: sourceTypeFilterOptions.value,
        multiple: true,
        placeholder: 'Pilih Sumber',
        label: 'Sumber',
    },
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal',
        label: 'Dari Tanggal',
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal',
        label: 'Sampai Tanggal',
    },
]);

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('costing.cost-entries.index'), {
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
    router.get(route('costing.cost-entries.index'), {
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
    <Head title="Catatan Biaya" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Catatan Biaya</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="costEntries"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :viewRoute="{ name: 'costing.cost-entries.show' }"
                        :indexRoute="{ name: 'costing.cost-entries.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="costing.cost-entries.index"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
