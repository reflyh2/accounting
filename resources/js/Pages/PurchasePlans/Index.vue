<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { renderStatusPillHtml } from '@/utils/statusPillHtml';

const props = defineProps({
    purchasePlans: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    companies: Array,
    branches: Array,
    statusOptions: Object,
});

const tableHeaders = [
    { key: 'plan_date', label: 'Tgl Rencana' },
    { key: 'plan_number', label: 'Nomor' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'required_date', label: 'Tgl Dibutuhkan' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    plan_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    required_date: (value) => (value ? new Date(value).toLocaleDateString('id-ID') : '-'),
    status: (value) => props.statusOptions?.[value] || value,
};

const columnRenderers = {
    status: (value) => renderStatusPillHtml(DocumentStatusKind.PURCHASE_PLAN, value, 'sm'),
};

const downloadOptions = [
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const currentSort = ref({ key: props.sort || 'plan_date', order: props.order || 'desc' });
const sortableColumns = ['plan_date', 'plan_number', 'status', 'required_date'];
const defaultSort = { key: 'plan_date', order: 'desc' };

const currentFilters = ref(props.filters || {});

const branchOptions = computed(() => props.branches.map((branch) => ({
    value: branch.id,
    label: branch.name,
    company_id: branch.company_id,
})));

const statusFilterOptions = computed(() =>
    Object.entries(props.statusOptions || {}).map(([value, label]) => ({
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
        name: 'branch_id',
        type: 'select',
        options: branchOptions.value,
        multiple: true,
        placeholder: 'Pilih Cabang',
        label: 'Cabang',
    },
    {
        name: 'status',
        type: 'select',
        options: statusFilterOptions.value,
        multiple: true,
        placeholder: 'Status',
        label: 'Status',
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
    router.get(route('purchase-plans.index'), {
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
    router.get(route('purchase-plans.index'), {
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

function deletePurchasePlan(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('purchase-plans.destroy', id), {
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

    router.delete(route('purchase-plans.bulk-delete'), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
            ids: ids,
        },
    });
}

</script>

<template>
    <Head title="Rencana Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Rencana Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="purchasePlans"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'purchase-plans.create' }"
                        :editRoute="{ name: 'purchase-plans.edit' }"
                        :viewRoute="{ name: 'purchase-plans.show' }"
                        :deleteRoute="{ name: 'purchase-plans.destroy' }"
                        :indexRoute="{ name: 'purchase-plans.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="purchase-plans.index"
                        :enableBulkActions="false"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="purchase-plans"
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deletePurchasePlan"
                        @bulkDelete="handleBulkDelete"
                    >
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
