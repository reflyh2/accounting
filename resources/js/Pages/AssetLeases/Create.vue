<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import LeaseForm from './Partials/LeaseForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    asset: Object,
});
</script>

<template>
    <Head title="Create Lease" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Create Lease</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('assets.show', asset.id)" :text="`Back to Asset: ${asset.name}`" />
                        </div>
                        <LeaseForm :asset="asset" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 