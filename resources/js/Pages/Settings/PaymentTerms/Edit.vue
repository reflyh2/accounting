<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PaymentTermForm from './Partials/PaymentTermForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    paymentTerm: Object,
    filters: Object,
    companies: Array,
});
</script>

<template>
    <Head title="Edit Payment Term" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Payment Term: {{ paymentTerm.name }}</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('settings.payment-terms.show', paymentTerm.id)" text="Kembali ke Detail" />
                        </div>
                        <PaymentTermForm
                            :paymentTerm="paymentTerm"
                            :companies="companies"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
