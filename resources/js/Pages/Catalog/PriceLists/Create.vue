<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PriceListForm from './Partials/PriceListForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    companies: Array,
    currencies: Array,
    partnerGroups: Array,
    channels: Object,
});
</script>

<template>
    <Head title="Buat Daftar Harga" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Daftar Harga Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.price-lists.index', filters)" text="Kembali ke Daftar Harga" />
                        </div>
                        <PriceListForm 
                            :companies="companies" 
                            :currencies="currencies" 
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
