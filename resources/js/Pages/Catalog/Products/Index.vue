<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppDataTable from '@/Components/AppDataTable.vue';

const props = defineProps({
    items: Object,
    filters: Object,
    categories: Array,
    perPage: [String, Number],
    sort: String,
    order: String,
    group: String,
    groupLabel: String,
    kindGroups: Object,
    kindOptions: Array,
});

const currentSort = ref({ key: props.sort || 'name', order: props.order || 'asc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'kind', label: 'Jenis' },
    { key: 'category.name', label: 'Kategori' },
    { key: 'cost_model', label: 'Cost Model' },
    { key: 'is_active', label: 'Aktif' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    is_active: (value) => value ? 'Ya' : 'Tidak',
    kind: (value) => formatKindLabel(value),
    cost_model: (value) => formatCostModel(value),
};

function formatKindLabel(kind) {
    const labels = {
        'goods_stock': 'Barang (Stok)',
        'goods_nonstock': 'Barang (Non-Stok)',
        'consumable': 'Consumable',
        'digital_good': 'Digital Good',
        'bundle': 'Bundle',
        'gift_card': 'Gift Card',
        'service_professional': 'Service (Professional)',
        'service_managed': 'Service (Managed)',
        'service_labor': 'Service (Labor)',
        'service_fee': 'Service (Fee)',
        'service_installation': 'Service (Installation)',
        'accommodation': 'Akomodasi',
        'venue_booking': 'Venue Booking',
        'event_ticket': 'Event Ticket',
        'tour_activity': 'Tour / Activity',
        'appointment': 'Appointment',
        'asset_rental': 'Asset Rental',
        'rental_with_operator': 'Rental + Operator',
        'lease': 'Lease',
        'air_ticket_resale': 'Air Ticket (Agency)',
        'train_ticket_resale': 'Train Ticket (Agency)',
        'bus_ferry_ticket_resale': 'Bus/Ferry (Agency)',
        'hotel_resale': 'Hotel (Agency)',
        'travel_package': 'Travel Package',
        'shipping_charge': 'Shipping Charge',
        'insurance_addon': 'Insurance Add-on',
        'deposit': 'Deposit',
        'penalty_fee': 'Penalty Fee',
        'membership': 'Membership',
    };
    return labels[kind] || kind;
}

function formatCostModel(model) {
    const labels = {
        'inventory_layer': 'Inventory Layer',
        'direct_expense_per_sale': 'Direct Expense',
        'job_costing': 'Job Costing',
        'asset_usage_costing': 'Asset Usage',
        'prepaid_consumption': 'Prepaid',
        'hybrid': 'Hybrid',
        'none': 'None',
    };
    return labels[model] || model;
}

const sortableColumns = ['code', 'name', 'kind', 'is_active'];
const defaultSort = { key: 'name', order: 'asc' };

function deleteItem(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';
    router.delete(route('catalog.products.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
}

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('catalog.products.index', { group: props.group }), {
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
    router.get(route('catalog.products.index', { group: props.group }), {
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

const customFilters = computed(() => [
    {
        name: 'kind',
        type: 'select',
        options: props.kindOptions || [],
        multiple: false,
        placeholder: 'Pilih Jenis',
        label: 'Jenis',
    },
    {
        name: 'product_category_id',
        type: 'select',
        options: (props.categories || []).map(c => ({ value: c.id, label: c.name })),
        multiple: false,
        placeholder: 'Pilih Kategori',
        label: 'Kategori',
    },
    {
        name: 'is_active',
        type: 'select',
        options: [{ value: 'true', label: 'Aktif' }, { value: 'false', label: 'Tidak Aktif' }],
        multiple: false,
        placeholder: 'Pilih Status',
        label: 'Status',
    },
]);

const groupTabs = computed(() => {
    return Object.entries(props.kindGroups || {}).map(([key, group]) => ({
        key,
        label: group.label,
        route: route('catalog.products.index', { group: key }),
        active: props.group === key,
    }));
});
</script>

<template>
    <Head :title="`Katalog - ${groupLabel}`" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Katalog Produk: {{ groupLabel }}</h2>
        </template>

        <div class="mx-auto">
            <!-- Group Tabs -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="flex space-x-4" aria-label="Tabs">
                    <Link
                        v-for="tab in groupTabs"
                        :key="tab.key"
                        :href="tab.route"
                        :class="[
                            tab.active
                                ? 'border-indigo-500 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm'
                        ]"
                    >
                        {{ tab.label }}
                    </Link>
                </nav>
            </div>

            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="items"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :customFilters="customFilters"
                        :createRoute="{ name: 'catalog.products.create', params: { group } }"
                        :editRoute="{ name: 'catalog.products.edit' }"
                        :deleteRoute="{ name: 'catalog.products.destroy' }"
                        :viewRoute="{ name: 'catalog.products.show' }"
                        :indexRoute="{ name: 'catalog.products.index', params: { group } }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        :routeName="`catalog.products.index`"
                        searchPlaceholder="Cari kode atau nama..."
                        @delete="deleteItem"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
