<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import SalesInvoiceForm from './Partials/SalesInvoiceForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    salesOrders: Array,
    selectedSalesOrders: Array,
    selectedPartnerId: Number,
    totalShippingCharge: Number,
    customers: Array,
    primaryCurrency: Object,
    products: Array,
    uoms: Array,
    companies: Array,
    branches: Array,
    currencies: Array,
    paymentMethods: Array,
    companyBankAccounts: Array,
    costItems: Array,
    users: Array,
    taxInvoiceCodeOptions: Object,
    defaultTaxInvoiceCode: String,
});
</script>

<template>
    <Head title="Buat Faktur Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Faktur Penjualan Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-invoices.index', filters)" text="Kembali ke Daftar Faktur Penjualan" />
                        </div>
                        <SalesInvoiceForm
                            :salesOrders="salesOrders"
                            :selectedSalesOrders="selectedSalesOrders"
                            :selectedPartnerId="selectedPartnerId"
                            :totalShippingCharge="totalShippingCharge"
                            :customers="customers"
                            :primaryCurrency="primaryCurrency"
                            :products="products"
                            :uoms="uoms"
                            :companies="companies"
                            :branches="branches"
                            :currencies="currencies"
                            :paymentMethods="paymentMethods"
                            :companyBankAccounts="companyBankAccounts"
                            :costItems="costItems"
                            :users="users"
                            :taxInvoiceCodeOptions="taxInvoiceCodeOptions"
                            :defaultTaxInvoiceCode="defaultTaxInvoiceCode"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
