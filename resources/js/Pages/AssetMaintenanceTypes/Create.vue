<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import MaintenanceTypeForm from './Partials/MaintenanceTypeForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    categories: Array,
    companies: Array,
    accounts: {
        type: Array,
        required: true
    }
});
</script>

<template>
    <Head title="Buat Tipe Pemeliharaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Tipe Pemeliharaan Aset Baru</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-maintenance-types.index', filters)" text="Kembali ke Daftar Tipe Pemeliharaan" />
                        </div>
                        <MaintenanceTypeForm :filters="filters" :categories="categories" :companies="companies" :accounts="accounts" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 