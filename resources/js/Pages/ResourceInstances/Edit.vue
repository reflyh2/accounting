<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ResourceInstanceForm from './Partials/ResourceInstanceForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    resourceInstance: Object,
    formOptions: Object,
    filters: Object,
});
</script>

<template>
    <Head title="Edit Resource Instance" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Data Resource Instance</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink 
                                :href="resourceInstance.resource_pool_id ? route('resource-pools.show', resourceInstance.resource_pool_id) : route('resource-instances.index')" 
                                :text="resourceInstance.resource_pool_id ? 'Kembali ke Resource Pool' : 'Kembali ke Daftar Resource Instance'" 
                            />
                        </div>
                        <ResourceInstanceForm 
                            :resourceInstance="resourceInstance" 
                            :formOptions="formOptions" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
