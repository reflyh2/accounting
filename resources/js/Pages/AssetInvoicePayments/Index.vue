<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';
import { formatNumber } from '@/utils/numberFormat';
import { PrinterIcon } from '@heroicons/vue/24/solid';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const props = defineProps({
    payments: Object,
    filters: Object,
    partners: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    paymentMethods: Object,
    paymentTypes: Object,
});

const currentSort = ref({ key: props.sort || 'payment_date', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'payment_date', label: 'Tanggal Bayar' },
    { key: 'number', label: 'Nomor Pembayaran' },
    { key: 'type', label: 'Tipe' },
    { key: 'partner.name', label: 'Partner' },
    { key: 'reference', label: 'Referensi' },
    { key: 'amount', label: 'Jumlah' },
    { key: 'payment_method', label: 'Metode Bayar' },
    { key: 'actions', label: '' }
];

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
        name: 'type',
        type: 'select',
        options: Object.entries(props.paymentTypes).map(([value, label]) => ({ value, label })),
        multiple: true,
        placeholder: 'Pilih tipe',
        label: 'Tipe Pembayaran'
    },
    {
        name: 'partner_id',
        type: 'select',
        options: props.partners.map(partner => ({ value: partner.id, label: partner.name })),
        multiple: true,
        placeholder: 'Pilih partner',
        label: 'Partner'
    },
    {
        name: 'payment_method',
        type: 'select',
        options: Object.entries(props.paymentMethods).map(([value, label]) => ({ value, label })),
        multiple: true,
        placeholder: 'Pilih metode bayar',
        label: 'Metode Pembayaran'
    }
]);

const downloadOptions = [
    { format: 'pdf', label: 'Download PDF' },
    { format: 'xlsx', label: 'Download Excel' },
    { format: 'csv', label: 'Download CSV' }
];

const columnFormatters = {
    payment_date: (value) => new Date(value).toLocaleDateString('id-ID'),
    amount: (value) => formatNumber(value),
    type: (value) => props.paymentTypes[value] || value,
    payment_method: (value) => props.paymentMethods[value] || value,
};

const sortableColumns = ['payment_date', 'number', 'type', 'partner.name', 'reference', 'amount', 'payment_method'];
const defaultSort = { key: 'payment_date', order: 'desc' };

function deletePayment(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('asset-invoice-payments.destroy', id), {
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

    router.delete(route('asset-invoice-payments.bulk-delete'), {
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
    router.get(route('asset-invoice-payments.index'), {
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
    router.get(route('asset-invoice-payments.index'), {
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
    <Head title="Pembayaran Faktur Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pembayaran Faktur Aset</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="payments"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'asset-invoice-payments.create' }"
                        :editRoute="{ name: 'asset-invoice-payments.edit' }"
                        :deleteRoute="{ name: 'asset-invoice-payments.destroy' }"
                        :viewRoute="{ name: 'asset-invoice-payments.show' }"
                        :indexRoute="{ name: 'asset-invoice-payments.index' }"
                        :downloadOptions="downloadOptions"
                        downloadBaseRoute="asset-invoice-payments"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="asset-invoice-payments.index"
                        @delete="deletePayment"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('asset-invoice-payments.print', item.id)" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 