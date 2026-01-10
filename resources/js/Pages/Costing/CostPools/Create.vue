<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CostPoolForm from './Partials/CostPoolForm.vue';

defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    assets: Array,
    poolTypeOptions: Object,
    allocationRuleOptions: Object,
});
</script>

<template>
    <Head title="Buat Pool Biaya" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Pool Biaya</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <CostPoolForm
                        :companies="companies"
                        :branches="branches"
                        :assets="assets"
                        :poolTypeOptions="poolTypeOptions"
                        :allocationRuleOptions="allocationRuleOptions"
                        :filters="filters"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
