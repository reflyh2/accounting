<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import GoodsForm from './Form.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    categories: Array,
    uoms: Array,
    taxCategories: Array,
    attributeSets: Array,
    companies: Array,
    typeTemplate: Object,
});
</script>

<template>
    <Head title="Tambah Barang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Barang</h2>
        </template>
        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.goods.index')" text="Back to Goods List" />
                        </div>
                        <GoodsForm
                            :categories="categories"
                            :uoms="uoms"
                            :taxCategories="taxCategories"
                            :attributeSets="attributeSets"
                            :companies="companies"
                            :typeTemplate="typeTemplate"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


