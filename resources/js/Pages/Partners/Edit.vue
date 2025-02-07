<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PartnerForm from './Partials/PartnerForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    partner: Object,
    companies: Array,
    filters: Object,
    statuses: Object,
    paymentTermTypes: Object,
});
</script>

<template>
    <Head title="Edit Partner" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Partner</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('partners.index', filters)" text="Back to Partners List" />
                        </div>
                        <PartnerForm 
                            :partner="partner"
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