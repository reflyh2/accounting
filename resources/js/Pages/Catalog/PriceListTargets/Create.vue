<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PriceListTargetForm from './Partials/PriceListTargetForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    priceLists: Array,
    companies: Array,
    partnerGroups: Array,
    channels: Object,
});
</script>

<template>
    <Head title="Buat Target Pelanggan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Target Pelanggan Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.price-list-targets.index', filters)" text="Kembali ke Target Pelanggan" />
                        </div>
                        <PriceListTargetForm 
                            :priceLists="priceLists" 
                            :companies="companies" 
                            :partnerGroups="partnerGroups"
                            :channels="channels"
                            :filters="filters" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
