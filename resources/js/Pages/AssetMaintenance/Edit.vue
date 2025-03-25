<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import MaintenanceForm from './Partials/MaintenanceForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    asset: Object,
    maintenance: Object,
    filters: Object,
    maintenanceTypes: Array,
});
</script>

<template>
    <Head title="Ubah Catatan Pemeliharaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Catatan Pemeliharaan</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink 
                                :href="route('asset-maintenance.index', asset.id)" 
                                :text="`Kembali ke Daftar Pemeliharaan: ${asset.name}`" 
                            />
                        </div>
                        <MaintenanceForm 
                            :asset="asset" 
                            :maintenance="maintenance" 
                            :filters="filters"
                            :maintenanceTypes="maintenanceTypes"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 