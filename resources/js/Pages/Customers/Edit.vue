<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CustomerForm from './Partials/CustomerForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    customer: Object,
    companies: Array,
    filters: Object,
    statuses: Object,
    paymentTermTypes: Object,
});
</script>

<template>
    <Head title="Ubah Pelanggan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Pelanggan</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('customers.index', filters)" text="Kembali ke Daftar Pelanggan" />
                        </div>
                        <CustomerForm 
                            :customer="customer"
                            :companies="companies"
                            :statuses="statuses"
                            :paymentTermTypes="paymentTermTypes"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 