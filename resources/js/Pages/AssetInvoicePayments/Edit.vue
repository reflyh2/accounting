<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetInvoicePaymentForm from './Partials/AssetInvoicePaymentForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    payment: Object,
    filters: Object,
    partners: Array,
    companies: Array,
    branches: Array,
    sourceAccounts: Array,
    paymentMethods: Object,
    paymentTypes: Object,
    assetInvoices: Array,
    currencies: Array,
});
</script>

<template>
    <Head title="Ubah Pembayaran Faktur Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Pembayaran Faktur Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-invoice-payments.index', filters)" text="Kembali ke Daftar Pembayaran" />
                        </div>
                        <AssetInvoicePaymentForm 
                            :payment="payment"
                            :partners="partners" 
                            :companies="companies"
                            :branches="branches"
                            :sourceAccounts="sourceAccounts"
                            :filters="filters" 
                            :paymentMethods="paymentMethods" 
                            :paymentTypes="paymentTypes"
                            :assetInvoices="assetInvoices"
                            :currencies="currencies"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 