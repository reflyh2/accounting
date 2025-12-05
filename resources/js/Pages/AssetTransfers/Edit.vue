<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetTransferForm from './Partials/AssetTransferForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    assetTransfer: Object,
    filters: Object,
    companies: Array,
    fromBranches: Array,
    toBranches: Array,
    assets: Array,
});
</script>

<template>
    <Head title="Ubah Transfer Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Data Transfer Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                             <AppBackLink :href="route('asset-transfers.index', filters)" text="Kembali ke Daftar Transfer" />
                        </div>
                        <AssetTransferForm 
                            :assetTransfer="assetTransfer" 
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