<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PurchasePlanForm from './Partials/PurchasePlanForm.vue';

defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    products: Array,
    uoms: Array,
});
</script>

<template>
    <Head title="Buat Rencana Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Rencana Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <PurchasePlanForm
                        :companies="companies"
                        :branches="branches"
                        :products="products"
                        :uoms="uoms"
                        :filters="filters"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
