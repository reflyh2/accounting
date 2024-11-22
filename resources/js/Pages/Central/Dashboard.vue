<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CentralLayout from '@/Layouts/CentralLayout.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';

const props = defineProps({
    tenants: Array,
});

function getImpersonationUrl(tenant) {
    return `http://${tenant.domain}/impersonate/${tenant.impersonation_token}`;
}
</script>

<template>
    <Head title="Central Dashboard" />

    <CentralLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Daftar Database</h2>
        </template>

        <template #header-right>
            <Link :href="route('register.tenant')">
                <AppPrimaryButton>Tambah Database</AppPrimaryButton>
            </Link>
        </template>

        <div class="py-12">
            <div class="min-w-min md:min-w-max mx-auto sm:px-6 lg:px-8">
                <div class="overflow-hidden">
                    <div class="text-gray-900">
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div v-for="tenant in tenants" :key="tenant.id" class="bg-white border text-center rounded-lg shadow-xl">
                                <div class="flex items-center justify-center my-8">
                                    <div class="w-24 h-24 bg-indigo-500 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                                        {{ tenant.name.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <a :href="getImpersonationUrl(tenant)" class="block w-full bg-indigo-500 text-white px-4 py-2 rounded-b-lg hover:bg-indigo-600 transition-colors">
                                    {{ tenant.name }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CentralLayout>
</template>