<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import EmployeeForm from './Partials/EmployeeForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    companies: Array,
    filters: Object,
    statuses: Object,
    paymentTermTypes: Object,
});
</script>

<template>
    <Head title="Buat Karyawan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Karyawan</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('employees.index', filters)" text="Kembali ke Daftar Karyawan" />
                        </div>
                        <EmployeeForm 
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