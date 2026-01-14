<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CostItemForm from './Partials/CostItemForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    costItem: Object,
    filters: Object,
    companies: Array,
    accounts: Array,
});
</script>

<template>
    <Head title="Edit Cost Item" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Cost Item: {{ costItem.name }}</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('costing.cost-items.show', costItem.id)" text="Kembali ke Detail" />
                        </div>
                        <CostItemForm
                            :costItem="costItem"
                            :companies="companies"
                            :accounts="accounts"
                            :filters="filters"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
