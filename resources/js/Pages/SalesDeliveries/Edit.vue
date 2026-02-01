<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesDeliveryForm from './Partials/SalesDeliveryForm.vue';

defineProps({
    delivery: Object,
    selectedSalesOrders: Array,
    selectedPartnerId: Number,
    locations: Array,
    filters: Object,
    costItems: Array,
    shippingProviders: Array,
    shippingTypeOptions: Object,
    cashBankAccounts: Array,
});
</script>

<template>
    <AuthenticatedLayout title="Edit Pengiriman Penjualan">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Pengiriman Penjualan
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="mb-4">
                        <AppBackLink :href="route('sales-deliveries.show', delivery.id)" text="Kembali ke Detail" />
                    </div>
                    <SalesDeliveryForm
                        mode="edit"
                        :delivery="delivery"
                        :selected-sales-orders="selectedSalesOrders"
                        :selected-partner-id="selectedPartnerId"
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
    </AuthenticatedLayout>
</template>
