<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PurchaseInvoiceForm from './Partials/PurchaseInvoiceForm.vue';

const props = defineProps({
    invoice: Object,
    filters: Object,
    purchaseOrders: Array,
    selectedPurchaseOrders: Array,
    selectedPartnerId: Number,
    suppliers: Array,
    paymentMethods: Array,
    partnerBankAccounts: Array,
    primaryCurrency: Object,
    products: Array,
    uoms: Array,
    companies: Array,
    branches: Array,
    currencies: Array,
});
</script>

<template>
    <Head :title="`Edit Faktur ${invoice?.invoice_number || ''}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Faktur Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <AppBackLink :href="route('purchase-invoices.index', filters)" text="Kembali ke Daftar Faktur" />

                    <PurchaseInvoiceForm
                        :invoice="invoice"
                        :purchaseOrders="purchaseOrders"
                        :selectedPurchaseOrders="selectedPurchaseOrders"
                        :selectedPartnerId="selectedPartnerId"
                        :suppliers="suppliers"
                        :paymentMethods="paymentMethods"
                        :partnerBankAccounts="partnerBankAccounts"
                        :primaryCurrency="primaryCurrency"
                        :products="products"
                        :uoms="uoms"
                        :companies="companies"
                        :branches="branches"
                        :currencies="currencies"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

