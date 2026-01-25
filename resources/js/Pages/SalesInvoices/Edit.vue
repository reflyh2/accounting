<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import SalesInvoiceForm from './Partials/SalesInvoiceForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    invoice: Object,
    isDirectInvoice: Boolean,
    selectedSalesOrders: Array,
    selectedPartnerId: Number,
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
    filters: Object,
    taxInvoiceCodeOptions: Object,
    defaultTaxInvoiceCode: String,
});
</script>

<template>
    <Head title="Ubah Faktur Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Data Faktur Penjualan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-invoices.index', filters)" text="Kembali ke Daftar Faktur Penjualan" />
                        </div>
                        <SalesInvoiceForm
                            :invoice="invoice"
                            :selectedSalesOrders="selectedSalesOrders"
                            :selectedPartnerId="selectedPartnerId"
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
