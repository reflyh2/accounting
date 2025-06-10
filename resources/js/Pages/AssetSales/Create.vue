<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetSaleForm from './Partials/AssetSaleForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    assets: Array,
    assetCategories: Array,
    currencies: Array,
});
</script>

<template>
    <Head title="Buat Faktur Penjualan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Faktur Penjualan Aset Baru</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-sales.index', filters)" text="Kembali ke Daftar Faktur Penjualan Aset" />
                        </div>
                        <AssetSaleForm 
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