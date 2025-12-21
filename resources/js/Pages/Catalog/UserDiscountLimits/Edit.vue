<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import UserDiscountLimitForm from './Partials/UserDiscountLimitForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    limit: Object,
    filters: Object,
    users: Array,
    products: Array,
    categories: Array,
});
</script>

<template>
    <Head title="Ubah Batas Diskon" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Batas Diskon</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.user-discount-limits.index', filters)" text="Kembali ke Daftar Batas Diskon" />
                        </div>
                        <UserDiscountLimitForm 
                            :limit="limit"
                            :users="users" 
                            :products="products" 
                            :categories="categories"
                            :filters="filters" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
