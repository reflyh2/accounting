<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ResourcePoolForm from './Partials/ResourcePoolForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    resourcePool: Object,
    formOptions: Object,
    filters: Object,
});
</script>

<template>
    <Head title="Edit Resource Pool" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Data Resource Pool</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('resource-pools.index', filters)" text="Kembali ke Daftar Resource Pool" />
                        </div>
                        <ResourcePoolForm :resourcePool="resourcePool" :formOptions="formOptions" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
