<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CurrencyForm from './Partials/CurrencyForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    currency: Object,
    companies: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Ubah Mata Uang" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Mata Uang</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('currencies.index', filters)" text="Kembali ke Daftar Mata Uang" />
                        </div>
                        <CurrencyForm :currency="currency" :companies="companies" :filters="filters" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>