<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppDataTable from '@/Components/AppDataTable.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';
import { renderStatusPillHtml } from '@/utils/statusPillHtml';

const props = defineProps({
    bookings: Object,
    filters: Object,
    perPage: [String, Number],
    sort: String,
    order: String,
    partners: Array,
    statusOptions: Array,
    bookingTypeOptions: Array,
});

const currentSort = ref({ key: props.sort || 'booked_at', order: props.order || 'desc' });
const currentFilters = ref(props.filters || {});

const tableHeaders = [
    { key: 'booked_at', label: 'Tanggal' },
    { key: 'booking_number', label: 'Nomor Booking' },
    { key: 'partner.name', label: 'Pelanggan' },
    { key: 'booking_type', label: 'Tipe' },
    { key: 'total_amount', label: 'Total' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

const columnFormatters = {
    booked_at: (value) => value ? new Date(value).toLocaleDateString('id-ID') : '-',
    total_amount: (value) => formatNumber(value),
    booking_type: (value) => value === 'accommodation' ? 'Akomodasi' : 'Rental',
};

const columnRenderers = {
    status: (value) => renderStatusPillHtml(DocumentStatusKind.BOOKING, value, 'sm'),
};

const partnerOptions = computed(() =>
    props.partners.map((p) => ({
        value: p.id,
        label: `${p.code} — ${p.name}`,
    }))
);

const statusFilterOptions = computed(() =>
    props.statusOptions.map((s) => ({
        value: s.value,
        label: s.label,
    }))
);

const customFilters = computed(() => [
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
    {
        name: 'partner_id',
        type: 'select',
        options: partnerOptions.value,
        multiple: true,
        placeholder: 'Pilih Pelanggan',
        label: 'Pelanggan',
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
        name: 'booking_type',
        type: 'select',
        options: props.bookingTypeOptions,
        placeholder: 'Tipe',
        label: 'Tipe Booking',
    },
]);

const sortableColumns = ['booked_at', 'booking_number', 'status', 'total_amount'];
const defaultSort = { key: 'booked_at', order: 'desc' };

function handleSort(newSort) {
    currentSort.value = newSort;
    router.get(route('bookings.index'), {
        ...currentFilters.value,
        sort: newSort.key,
        order: newSort.order,
        per_page: props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function handleFilter(newFilters) {
    currentFilters.value = newFilters;
    router.get(route('bookings.index'), {
        ...currentFilters.value,
        sort: currentSort.value.key,
        order: currentSort.value.order,
        per_page: newFilters.per_page || props.perPage,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function deleteBooking(id) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('bookings.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        data: {
            preserveState: true,
            currentQuery: currentQuery,
        },
    });
}

function handleBulkDelete(ids) {
    const page = usePage();
    const currentQuery = page.url.includes('?') ? page.url.split('?')[1] : '';

    router.delete(route('bookings.bulk-delete'), {
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
    <Head title="Booking" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Daftar Booking</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="text-gray-900">
                    <AppDataTable
                        :data="bookings"
                        :filters="currentFilters"
                        :tableHeaders="tableHeaders"
                        :columnFormatters="columnFormatters"
                        :columnRenderers="columnRenderers"
                        :customFilters="customFilters"
                        :editRoute="{ name: 'bookings.edit' }"
                        :viewRoute="{ name: 'bookings.show' }"
                        :deleteRoute="{ name: 'bookings.destroy' }"
                        :indexRoute="{ name: 'bookings.index' }"
                        :sortable="sortableColumns"
                        :defaultSort="defaultSort"
                        :currentSort="currentSort"
                        :perPage="perPage"
                        routeName="bookings.index"
                        @delete="deleteBooking"
                        @bulkDelete="handleBulkDelete"
                        @sort="handleSort"
                        @filter="handleFilter"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
