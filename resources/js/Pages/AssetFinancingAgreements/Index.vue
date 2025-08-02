<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    agreements: Object,
    filters: Object,
    partners: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    statusOptions: Object,
    paymentFrequencyOptions: Object,
    interestCalculationMethodOptions: Object,
});

const tabs = [
    { label: 'Perjanjian Pembiayaan Aset', route: 'asset-financing-agreements.index', active: true },
    { label: 'Pembayaran Pembiayaan Aset', route: 'asset-financing-payments.index', active: false },
];

const currentSort = ref({ key: props.sort || 'agreement_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'agreement_date', label: 'Tanggal Perjanjian' },
    { key: 'number', label: 'Nomor Perjanjian' },
    { key: 'creditor.name', label: 'Kreditor' },
    { key: 'total_amount', label: 'Total Jumlah' },
    { key: 'interest_rate', label: 'Suku Bunga (%)' },
    { key: 'interest_calculation_method', label: 'Metode Perhitungan Bunga' },
    { key: 'payment_frequency', label: 'Frekuensi Pembayaran' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
];

const partnerOptions = computed(() => 
    props.partners.map(partner => ({ value: partner.id, label: partner.name }))
);

const statusSelectOptions = computed(() => 
    Object.entries(props.statusOptions).map(([value, label]) => ({ value, label }))
);

const paymentFrequencySelectOptions = computed(() => 
    Object.entries(props.paymentFrequencyOptions).map(([value, label]) => ({ value, label }))
);

const customFilters = computed(() => [
    {
        name: 'from_date',
        type: 'date',
        placeholder: 'Dari Tanggal',
        label: 'Dari Tanggal'
    },
    {
        name: 'to_date',
        type: 'date',
        placeholder: 'Sampai Tanggal',
        label: 'Sampai Tanggal'
    },
    {
        name: 'creditor_id',
        type: 'select',
        options: partnerOptions.value,
        multiple: true,
        placeholder: 'Pilih kreditor',
        label: 'Kreditor'
    },
    {
        name: 'status',
        type: 'select',
        options: statusSelectOptions.value,
        multiple: true,
        placeholder: 'Pilih status',
        label: 'Status'
    },
    {
        name: 'payment_frequency',
        type: 'select',
        options: paymentFrequencySelectOptions.value,
        multiple: true,
        placeholder: 'Pilih frekuensi pembayaran',
        label: 'Frekuensi Pembayaran'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const paymentFrequencyLabels = {
    'monthly': 'Bulanan',
    'quarterly': 'Triwulanan',
    'semi-annually': 'Setengah Tahunan',
    'annually': 'Tahunan',
};

const columnFormatters = {
    agreement_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    total_amount: (value) => formatNumber(value),
    interest_rate: (value) => formatNumber(value) + '%',
    interest_calculation_method: (value) => props.interestCalculationMethodOptions[value],
    payment_frequency: (value) => paymentFrequencyLabels[value],
    status: (value) => props.statusOptions[value],
};

const sortableColumns = ['agreement_date', 'number', 'creditor.name', 'total_amount', 'interest_rate', 'payment_frequency', 'status'];
const defaultSort = { key: 'agreement_date', order: 'desc' };

function deleteAgreement(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-financing-agreements.destroy', id), {
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

    router.delete(route('asset-financing-agreements.bulk-delete'), {
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
    router.get(route('asset-financing-agreements.index'), {
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
    router.get(route('asset-financing-agreements.index'), {
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
    <Head title="Perjanjian Pembiayaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Perjanjian Pembiayaan Aset</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <TabLinks :tabs="tabs" />

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="agreements"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-financing-agreements.create' }"
                        :editRoute="{ name: 'asset-financing-agreements.edit' }"
                        :deleteRoute="{ name: 'asset-financing-agreements.destroy' }"
                        :viewRoute="{ name: 'asset-financing-agreements.show' }"
                        :indexRoute="{ name: 'asset-financing-agreements.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-financing-agreements"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-financing-agreements.index"
                        @delete="deleteAgreement"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('asset-financing-agreements.print', item.id)" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 