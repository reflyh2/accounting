<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetPurchaseForm from './Partials/AssetPurchaseForm.vue'; // Use the new form partial
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
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
    <Head title="Buat Faktur Pembelian Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Faktur Pembelian Aset Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-purchases.index', filters)" text="Kembali ke Daftar Faktur Pembelian Aset" />
                        </div>
                        <AssetPurchaseForm 
                            :companies="companies" 
                            :branches="branches" 
                            :partners="partners"
                            :assets="assets"
                            :asset-categories="assetCategories"
                            :currencies="currencies"
                            :filters="filters" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 