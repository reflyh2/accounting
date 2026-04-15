<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesDeliveryForm from './Partials/SalesDeliveryForm.vue';

defineProps({
    delivery: Object,
    companies: Array,
    branches: Array,
    salesOrders: Array,
    selectedSalesOrders: Array,
    selectedPartnerId: Number,
    customers: Array,
    locations: Array,
    filters: Object,
    costItems: Array,
    shippingProviders: Array,
    shippingTypeOptions: Object,
    cashBankAccounts: Array,
});
</script>

<template>
    <Head title="Ubah Pengiriman Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Pengiriman Penjualan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-deliveries.show', delivery.id)" text="Kembali ke Detail" />
                        </div>
                        <SalesDeliveryForm
                            mode="edit"
                            :delivery="delivery"
                            :companies="companies"
                            :branches="branches"
                            :sales-orders="salesOrders"
                            :selected-sales-orders="selectedSalesOrders"
                            :selected-partner-id="selectedPartnerId"
                            :customers="customers"
                            :locations="locations"
                            :cost-items="costItems"
                            :shipping-providers="shippingProviders"
                            :shipping-type-options="shippingTypeOptions"
                            :cash-bank-accounts="cashBankAccounts"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
