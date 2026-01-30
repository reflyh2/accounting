<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ShippingProviderForm from './Partials/ShippingProviderForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    shippingProvider: Object,
    filters: Object,
    typeOptions: Object,
});
</script>

<template>
    <Head title="Edit Penyedia Pengiriman" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Penyedia Pengiriman</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('shipping-providers.index', filters)" text="Kembali ke Daftar Penyedia Pengiriman" />
                        </div>
                        <ShippingProviderForm
                            :shippingProvider="shippingProvider"
                            :typeOptions="typeOptions"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
