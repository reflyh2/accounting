<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CostPoolForm from './Partials/CostPoolForm.vue';

defineProps({
    costPool: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    assets: Array,
    poolTypeOptions: Object,
    allocationRuleOptions: Object,
});
</script>

<template>
    <Head :title="'Edit Pool Biaya: ' + costPool.code" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Pool Biaya: {{ costPool.code }}</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900">
                    <CostPoolForm
                        :costPool="costPool"
                        :companies="companies"
                        :branches="branches"
                        :assets="assets"
                        :poolTypeOptions="poolTypeOptions"
                        :allocationRuleOptions="allocationRuleOptions"
                        :filters="filters"
                        isEdit
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
