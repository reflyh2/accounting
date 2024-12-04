<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import MemberForm from './Partials/MemberForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    member: Object,
    companies: Array,
    filters: Object,
    statuses: Object,
    paymentTermTypes: Object,
});
</script>

<template>
    <Head title="Ubah Anggota" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Anggota</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('members.index', filters)" text="Kembali ke Daftar Anggota" />
                        </div>
                        <MemberForm 
                            :member="member"
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