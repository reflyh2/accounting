<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import TransferForm from './Partials/TransferForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    asset: Object,
    transfer: Object,
});
</script>

<template>
    <Head title="Edit Transfer Request" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Transfer Request</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-transfers.show', [asset.id, transfer.id])" text="Back to Transfer Details" />
                        </div>
                        <TransferForm :asset="asset" :transfer="transfer" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 