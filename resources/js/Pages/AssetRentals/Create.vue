<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetRentalForm from './Partials/AssetRentalForm.vue';
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
    <Head title="Buat Faktur Sewa Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Faktur Sewa Aset Baru</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-rentals.index', filters)" text="Kembali ke Daftar Faktur Penyewaan" />
                        </div>
                        <AssetRentalForm 
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