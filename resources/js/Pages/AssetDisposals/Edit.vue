<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import DisposalForm from './Partials/DisposalForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    asset: Object,
    disposal: Object,
    currentValue: Number,
});
</script>

<template>
    <Head title="Edit Disposal Request" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Disposal Request</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-disposals.show', [asset.id, disposal.id])" text="Back to Disposal Details" />
                        </div>
                        <DisposalForm :asset="asset" :disposal="disposal" :current-value="currentValue" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 