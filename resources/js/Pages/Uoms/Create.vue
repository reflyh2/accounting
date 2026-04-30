<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import UomForm from './Partials/UomForm.vue';

defineProps({
    companies: Array,
    kinds: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Tambah Satuan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Satuan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('uoms.index', filters)" text="Kembali ke Daftar Satuan" />
                    </div>
                    <UomForm :companies="companies" :kinds="kinds" :filters="filters" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
