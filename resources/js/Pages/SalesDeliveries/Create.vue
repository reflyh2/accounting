<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesDeliveryForm from './Partials/SalesDeliveryForm.vue';

const props = defineProps({
    companies: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    salesOrders: Array,
    selectedSalesOrders: Array,
    selectedCompanyId: Number,
    selectedBranchId: Number,
    selectedPartnerId: Number,
    customers: Array,
    locations: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Buat Pengiriman Penjualan" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Pengiriman Penjualan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6 space-y-6">
                <AppBackLink :href="route('sales-deliveries.index', filters)" text="Kembali ke daftar pengiriman" />

                <SalesDeliveryForm
                    :companies="companies"
                    :branches="branches"
                    :salesOrders="salesOrders"
                    :selectedSalesOrders="selectedSalesOrders"
                    :selectedCompanyId="selectedCompanyId"
                    :selectedBranchId="selectedBranchId"
                    :selectedPartnerId="selectedPartnerId"
                    :customers="customers"
                    :locations="locations"
                    :filters="filters"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
