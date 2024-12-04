<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import MaintenanceForm from './Partials/MaintenanceForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    asset: Object,
    filters: Object,
});
</script>

<template>
    <Head title="Buat Catatan Pemeliharaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Catatan Pemeliharaan Baru</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink 
                                :href="route('asset-maintenance.index', asset.id)" 
                                :text="`Kembali ke Daftar Pemeliharaan: ${asset.name}`" 
                            />
                        </div>
                        <MaintenanceForm :asset="asset" :filters="filters" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 