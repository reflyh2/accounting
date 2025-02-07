<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import MemberForm from './Partials/MemberForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    companies: Array,
    filters: Object,
    statuses: Object,
    paymentTermTypes: Object,
});
</script>

<template>
    <Head title="Buat Anggota" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Anggota</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('members.index', filters)" text="Kembali ke Daftar Anggota" />
                        </div>
                        <MemberForm 
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