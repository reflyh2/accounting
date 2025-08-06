<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetDisposalForm from './Partials/AssetDisposalForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    proceedAccounts: Array,
    assets: Array,
    statusOptions: Object,
    disposalTypeOptions: Object,
});
</script>

<template>
    <Head title="Buat Dokumen Pelepasan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Dokumen Pelepasan Aset Baru</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-disposals.index', filters)" text="Kembali ke Daftar Pelepasan Aset" />
                        </div>
                        <AssetDisposalForm 
                            :companies="companies" 
                            :branches="branches" 
                            :proceed-accounts="proceedAccounts"
                            :assets="assets"
                            :status-options="statusOptions"
                            :disposal-type-options="disposalTypeOptions"
                            :filters="filters" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 