<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PurchasePlanForm from './Partials/PurchasePlanForm.vue';

defineProps({
    purchasePlan: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    products: Array,
    uoms: Array,
});
</script>

<template>
    <Head :title="'Edit Rencana Pembelian: ' + purchasePlan.plan_number" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Rencana Pembelian: {{ purchasePlan.plan_number }}</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <PurchasePlanForm
                        :purchasePlan="purchasePlan"
                        :companies="companies"
                        :branches="branches"
                        :products="products"
                        :uoms="uoms"
                        :filters="filters"
                        isEdit
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
