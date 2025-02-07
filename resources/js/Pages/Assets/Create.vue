<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetForm from './Partials/AssetForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    companies: {
        type: Array,
        required: true
    },
    branches: {
        type: Array,
        required: true
    },
    categories: {
        type: Array,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    },
});
</script>

<template>
    <Head title="Tambah Aset Baru" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Aset Baru</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('assets.index', filters)" text="Kembali ke Daftar Aset" />
                    </div>
                    <AssetForm
                        :companies="companies"
                        :branches="branches"
                        :categories="categories"
                        :filters="filters"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 