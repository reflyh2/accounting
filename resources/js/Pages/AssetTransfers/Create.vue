<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetTransferForm from './Partials/AssetTransferForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    companies: Array,
    fromBranches: Array,
    toBranches: Array,
    assets: Array,
});
</script>

<template>
    <Head title="Buat Transfer Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Transfer Aset Baru</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-transfers.index', filters)" text="Kembali ke Daftar Transfer Aset" />
                        </div>
                        <AssetTransferForm 
                            :companies="companies" 
                            :from-branches="fromBranches" 
                            :to-branches="toBranches"
                            :assets="assets"
                            :filters="filters" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 