<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import { formatNumber } from '@/utils/numberFormat';
import { renderStatusPillHtml } from '@/utils/statusPillHtml';
import { DocumentStatusKind } from '@/constants/documentStatuses';

const props = defineProps({
    deliveries: Object,
    filters: {
        type: Object,
        default: () => ({}),
    },
    companies: Array,
    branches: Array,
    customers: Array,
    statusOptions: Object,
    perPage: [Number, String],
    sort: String,
    order: String,
});

const currentFilters = ref({ ...props.filters });
const currentSort = ref({ key: props.sort || 'delivery_date', order: props.order || 'desc' });

const tableHeaders = [
    { key: 'delivery_date', label: 'Tanggal' },
    { key: 'delivery_number', label: 'Nomor Delivery' },
    { key: 'sales_orders', label: 'Sales Orders' },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'branch_name', label: 'Cabang' },
    { key: 'status', label: 'Status' },
    { key: 'total_quantity', label: 'Total Qty' },
    { key: 'total_cogs', label: 'Total COGS' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    delivery_date: (value) => (value ? new Date(value).toLocaleDateString('id-ID') : '-'),
    total_quantity: (value) => formatNumber(value ?? 0),
    total_cogs: (value) => formatNumber(value ?? 0),
};

const columnRenderers = {
    status: (value) => renderStatusPillHtml(DocumentStatusKind.DELIVERY, value, 'sm'),
    sales_orders: (value) => value && value.length
        ? '<ul class="list-disc">' + value.map(so => 
            `<li class="mb-1"><a href="${route('sales-orders.show', so.id)}" target="_blank" class="bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 text-xs px-2 py-1 rounded-full">${so.order_number}</a></li>`
          ).join('') + '</ul>'
        : '-',
};

const customFilters = computed(() => [
    {
        name: 'search',
        type: 'text',
        placeholder: 'Cari nomor delivery atau SO',
        label: 'Pencarian',
    },
    {
        name: 'date_from',
        type: 'date',
        placeholder: 'Dari tanggal',
        label: 'Tanggal Mulai',
    },
    {
        name: 'date_to',
        type: 'date',
        placeholder: 'Sampai tanggal',
        label: 'Tanggal Selesai',
    },
    {
        name: 'company_id',
        type: 'select',
        multiple: true,
        options: props.companies.map((company) => ({ value: company.id, label: company.name })),
        placeholder: 'Pilih perusahaan',
        label: 'Perusahaan',
    },
    {
        name: 'branch_id',
        type: 'select',
        multiple: true,
        options: props.branches.map((branch) => ({ value: branch.id, label: branch.name })),
        placeholder: 'Pilih cabang',
        label: 'Cabang',
    },
    {
        name: 'partner_id',
        type: 'select',
        multiple: true,
        options: props.customers.map((customer) => ({ value: customer.id, label: customer.name })),
        placeholder: 'Pilih pelanggan',
        label: 'Pelanggan',
    },
]);

const sortableColumns = ['delivery_date', 'delivery_number'];
const defaultSort = { key: 'delivery_date', order: 'desc' };

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(
        route('sales-deliveries.index'),
        {
            ...currentFilters.value,
            sort: newSort.key,
            order: newSort.order,
            per_page: props.perPage,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(
        route('sales-deliveries.index'),
        {
            ...currentFilters.value,
            sort: currentSort.value.key,
            order: currentSort.value.order,
            per_page: newFilters.per_page || props.perPage,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function deleteSalesDelivery(id) {
    router.delete(route('sales-deliveries.destroy', id));
}
</script>

<template>
    <Head title="Pengiriman Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengiriman Penjualan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="deliveries"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'sales-deliveries.create' }"
                        :viewRoute="{ name: 'sales-deliveries.show' }"
                        :indexRoute="{ name: 'sales-deliveries.index' }"
                        :editRoute="{ name: 'sales-deliveries.edit' }"
                        :deleteRoute="{ name: 'sales-deliveries.destroy' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="sales-deliveries.index"
                        itemKey="id"
                        searchPlaceholder="Cari nomor delivery atau SO..."
                        @sort="handleSort"
                        @filter="handleFilter"
                        @delete="deleteSalesDelivery"
                    >
                        <template #custom_actions="{ item }">
                            <a :href="route('sales-deliveries.print', item.id)" target="_blank">
                                <AppPrintButton />
                            </a>
                        </template>
                    </AppDataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

