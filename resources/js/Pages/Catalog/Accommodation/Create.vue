<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AccommodationForm from './Form.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    categories: Array,
    taxCategories: Array,
    attributeSets: Array,
    companies: Array,
    typeTemplate: Object,
});
</script>

<template>
    <Head title="Tambah Akomodasi" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Akomodasi</h2>
        </template>
        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.accommodation.index')" text="Back to Accommodation List" />
                        </div>
                        <AccommodationForm
                            :categories="categories"
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

