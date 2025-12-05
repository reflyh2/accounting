<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ProductCategoryForm from './Partials/ProductCategoryForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    category: Object,
    companies: Array,
    attributeSets: Array,
    parentCategories: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Ubah Kategori Produk" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Kategori Produk</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('catalog.product-categories.index', filters)" text="Kembali ke Kategori Produk" />
                    </div>
                    <ProductCategoryForm
                        :category="category"
                        :companies="companies"
                        :attributeSets="attributeSets"
                        :parentCategories="parentCategories"
                        :filters="filters"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

