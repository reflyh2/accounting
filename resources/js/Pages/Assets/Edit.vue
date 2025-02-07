<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetForm from './Partials/AssetForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    asset: Object,
    filters: Object,
    categories: Array,
    companies: Array,
    branches: Array,
});
</script>

<template>
    <Head title="Edit Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('assets.index', filters)" text="Kembali ke Daftar Aset" />
                        </div>
                        <AssetForm :asset="asset" :categories="categories" :filters="filters" :companies="companies" :branches="branches" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 