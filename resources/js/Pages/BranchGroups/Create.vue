<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import BranchGroupForm from './Partials/BranchGroupForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

const props = defineProps({
    companies: Array,
});
</script>

<template>
    <Head title="Buat Kelompok Cabang" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Kelompok Cabang</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                           <AppBackLink :href="route('branch-groups.index', filters)" text="Kembali ke Daftar Kelompok Cabang" />
                        </div>
                        <BranchGroupForm :companies="companies" />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>