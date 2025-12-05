<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetPurchaseForm from './Partials/AssetPurchaseForm.vue'; // Use the new form partial
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    assetPurchase: Object, // Renamed from journal
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array, // Added partners
    assets: Array,   // Added assets
    assetCategories: Array,
    currencies: Array,
});
</script>

<template>
    <Head title="Ubah Faktur Pembelian Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Data Faktur Pembelian Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                             <AppBackLink :href="route('asset-purchases.index', filters)" text="Kembali ke Daftar Faktur Pembelian" />
                        </div>
                        <AssetPurchaseForm 
                            :assetPurchase="assetPurchase" 
                            :companies="companies" 
                            :branches="branches" 
                            :partners="partners"
                            :assets="assets"
                            :filters="filters" 
                            :assetCategories="assetCategories"
                            :currencies="currencies"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 