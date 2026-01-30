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
    costItems: Array,
    shippingTypeOptions: Array,
    shippingProviders: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Buat Pengiriman Penjualan" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Pengiriman Penjualan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-deliveries.index', filters)" text="Kembali ke Daftar Pengiriman" />
                        </div>
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
                            :costItems="costItems"
                            :shippingTypeOptions="shippingTypeOptions"
                            :shippingProviders="shippingProviders"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
