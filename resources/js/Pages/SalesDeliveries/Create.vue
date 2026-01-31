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
    cashBankAccounts: Array,
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
                            mode="create"
                            :companies="companies"
                            :branches="branches"
                            :sales-orders="salesOrders"
                            :selected-sales-orders="selectedSalesOrders"
                            :selected-company-id="selectedCompanyId"
                            :selected-branch-id="selectedBranchId"
                            :selected-partner-id="selectedPartnerId"
                            :customers="customers"
                            :locations="locations"
                            :cost-items="costItems"
                            :shipping-type-options="shippingTypeOptions"
                            :shipping-providers="shippingProviders"
                            :cash-bank-accounts="cashBankAccounts"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
