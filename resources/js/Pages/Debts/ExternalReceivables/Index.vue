<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import DebtTabs from '@/Tabs/DebtTabs.vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    items: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    statusOptions: Object,
    statusStyles: Object,
});

const currentSort = ref({ key: props.sort || 'issue_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'issue_date', label: 'Tanggal' },
    { key: 'number', label: 'Nomor' },
    { key: 'partner.name', label: 'Partner' },
    { key: 'branch.branch_group.company.name', label: 'Perusahaan' },
    { key: 'branch.name', label: 'Cabang' },
    { key: 'currency.code', label: 'Mata Uang' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'due_date', label: 'Jatuh Tempo' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const branchOptions = computed(() => props.branches.map(b => ({ value: b.id, label: b.name })));
const partnerOptions = computed(() => props.partners.map(p => ({ value: p.id, label: p.name })));

const customFilters = computed(() => [
    { name: 'from_date', type: 'date', placeholder: 'Dari Tanggal', label: 'Dari' },
    { name: 'to_date', type: 'date', placeholder: 'Sampai Tanggal', label: 'Sampai' },
    { name: 'company_id', type: 'select', options: props.companies.map(c => ({ value: c.id, label: c.name })), multiple: true, placeholder: 'Pilih Perusahaan', label: 'Perusahaan' },
    { name: 'branch_id', type: 'select', options: branchOptions.value, multiple: true, placeholder: 'Pilih Cabang', label: 'Cabang' },
    { name: 'partner_id', type: 'select', options: partnerOptions.value, multiple: true, placeholder: 'Pilih Partner', label: 'Partner' },
]);

const downloadOptions = [
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    issue_date: (v) => new Date(v).toLocaleDateString('id-ID'),
    due_date: (v) => v ? new Date(v).toLocaleDateString('id-ID') : '-',
    amount: (v) => `${formatNumber(v)}`,
};

const columnRenderers = {
    status: (value) => {
        const style = props.statusStyles?.[value] || { label: value, class: 'bg-gray-100 text-gray-800' };
        return `<span class=\"px-2 py-1 text-xs font-medium rounded-full ${style.class}\">${style.label}</span>`;
    }
};

const sortableColumns = ['issue_date', 'number', 'external_debt.partner.name', 'branch.name', 'due_date', 'amount', 'status'];
const defaultSort = { key: 'issue_date', order: 'desc' };

function deleteItem(id) {
    router.delete(route('external-receivables.destroy', id), { preserveScroll: true, preserveState: true });
}

function handleBulkDelete(ids) {}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('external-receivables.index'), { ...route().params, ...currentFilters.value, sort: newSort.key, order: newSort.order, per_page: props.perPage }, { preserveState: true, preserveScroll: true, replace: true });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    if (newFilters.page) delete newFilters.page;
    router.get(route('external-receivables.index'), { ...currentFilters.value, sort: currentSort.value.key, order: currentSort.value.order, per_page: newFilters.per_page || props.perPage, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
}

</script>

<template>
    <Head title="Piutang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Piutang</h2>
        </template>

        <div class="mx-auto">
            <DebtTabs activeTab="external-receivables.index" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="items"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'external-receivables.create' }"
                        :editRoute="{ name: 'external-receivables.edit' }"
                        :deleteRoute="{ name: 'external-receivables.destroy' }"
                        :viewRoute="{ name: 'external-receivables.show' }"
                        :indexRoute="{ name: 'external-receivables.index' }"
                        :downloadOptions="downloadOptions"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="external-receivables.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor, partner, cabang..."
                        @delete="deleteItem"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


