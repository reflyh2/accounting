<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AssetMaintenanceForm from './Partials/AssetMaintenanceForm.vue';

defineProps({
    companies: Array,
    branches: Array,
    assets: Array,
    vendors: Array,
    filters: Object,
    maintenanceTypes: Object,
    statusOptions: Object,
});
</script>

<template>
    <Head title="Tambah Pemeliharaan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Pemeliharaan Aset</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <div class="mb-6">
                    <AppBackLink :href="route('asset-maintenances.index', filters)" text="Kembali ke Daftar Pemeliharaan" />
                </div>

                <AssetMaintenanceForm
                    :companies="companies"
                    :branches="branches"
                    :assets="assets"
                    :vendors="vendors"
                    :filters="filters"
                    :maintenanceTypes="maintenanceTypes"
                    :statusOptions="statusOptions"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
