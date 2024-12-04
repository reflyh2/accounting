<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CategoryForm from './Partials/CategoryForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    companies: Array,
});
</script>

<template>
    <Head title="Buat Kategori Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Kategori Aset Baru</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-categories.index', filters)" text="Kembali ke Daftar Kategori" />
                        </div>
                        <CategoryForm :filters="filters" :companies="companies" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 