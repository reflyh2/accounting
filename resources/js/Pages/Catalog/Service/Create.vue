<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ServiceForm from './Form.vue';
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
    <Head title="Tambah Jasa" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Jasa</h2>
        </template>
        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.services.index')" text="Back to Service List" />
                        </div>
                        <ServiceForm
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

