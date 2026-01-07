<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesDeliveryEditForm from './Partials/SalesDeliveryEditForm.vue';

const props = defineProps({
    delivery: Object,
    selectedSalesOrders: Array,
    selectedPartnerId: Number,
    locations: Array,
    filters: Object,
});
</script>

<template>
    <Head :title="`Edit Pengiriman ${delivery.delivery_number}`" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Pengiriman Penjualan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-deliveries.show', delivery.id)" text="Kembali ke detail pengiriman" />
                        </div>
                        <SalesDeliveryEditForm
                            :delivery="delivery"
                            :selectedSalesOrders="selectedSalesOrders"
                            :selectedPartnerId="selectedPartnerId"
                            :locations="locations"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
