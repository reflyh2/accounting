<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PurchaseInvoiceForm from './Partials/PurchaseInvoiceForm.vue';

const props = defineProps({
    filters: Object,
    purchaseOrder: Object,
    defaultLines: Array,
    selectedPurchaseOrderLabel: String,
    purchaseOrderSearchUrl: String,
    primaryCurrency: Object,
});
</script>

<template>
    <Head title="Buat Faktur Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Faktur Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <AppBackLink :href="route('purchase-invoices.index', filters)" text="Kembali ke Daftar Faktur" />

                    <PurchaseInvoiceForm
                        :invoice="null"
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

