<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetRentalForm from './Partials/AssetRentalForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    assetRental: Object,
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
    <Head title="Ubah Faktur Sewa Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Data Faktur Sewa Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                             <AppBackLink :href="route('asset-rentals.index', filters)" text="Kembali ke Daftar Faktur Penyewaan" />
                        </div>
                        <AssetRentalForm 
                            :assetRental="assetRental" 
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