<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PurchaseInvoiceForm from './Partials/PurchaseInvoiceForm.vue';

const props = defineProps({
    invoice: Object,
    filters: Object,
    purchaseOrder: Object,
    defaultLines: Array,
    selectedPurchaseOrderLabel: String,
    purchaseOrderSearchUrl: String,
    primaryCurrency: Object,
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
                        :purchaseOrder="purchaseOrder"
                        :defaultLines="defaultLines"
                        :selectedPurchaseOrderLabel="selectedPurchaseOrderLabel"
                        :purchaseOrderSearchUrl="purchaseOrderSearchUrl"
                        :primaryCurrency="primaryCurrency"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

