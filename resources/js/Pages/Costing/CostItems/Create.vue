<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CostItemForm from './Partials/CostItemForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    filters: Object,
    companies: Array,
    accounts: Array,
});
</script>

<template>
    <Head title="Buat Cost Item" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Cost Item Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('costing.cost-items.index', filters)" text="Kembali ke Daftar Cost Items" />
                        </div>
                        <CostItemForm
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
