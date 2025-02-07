<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CategoryForm from './Partials/CategoryForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    category: Object,
    filters: Object,
    companies: Array,
    accounts: {
        type: Array,
        required: true
    }
});
</script>

<template>
    <Head title="Ubah Kategori Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Kategori Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-categories.index', filters)" text="Kembali ke Daftar Kategori" />
                        </div>
                        <CategoryForm :category="category" :filters="filters" :companies="companies" :accounts="accounts" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 