<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PriceListItemForm from './Partials/PriceListItemForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    priceLists: Array,
    products: Array,
    uoms: Array,
});
</script>

<template>
    <Head title="Buat Harga" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Harga Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.price-list-items.index', filters)" text="Kembali ke Harga" />
                        </div>
                        <PriceListItemForm 
                            :priceLists="priceLists" 
                            :products="products" 
                            :uoms="uoms"
                            :filters="filters" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
