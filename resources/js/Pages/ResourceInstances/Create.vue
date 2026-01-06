<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ResourceInstanceForm from './Partials/ResourceInstanceForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    formOptions: Object,
    preselectedPoolId: [String, Number],
});
</script>

<template>
    <Head title="Buat Resource Instance" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Resource Instance Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink 
                                :href="preselectedPoolId ? route('resource-pools.show', preselectedPoolId) : route('resource-instances.index')" 
                                :text="preselectedPoolId ? 'Kembali ke Resource Pool' : 'Kembali ke Daftar Resource Instance'" 
                            />
                        </div>
                        <ResourceInstanceForm 
                            :formOptions="formOptions" 
                            :preselectedPoolId="preselectedPoolId" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
