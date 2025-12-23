<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CompanyBankAccountForm from './Partials/CompanyBankAccountForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    bankAccount: Object,
    companies: Array,
    accounts: Array,
    currencies: Array,
    filters: Object,
});
</script>

<template>
    <Head title="Edit Rekening Bank Perusahaan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Edit Rekening Bank Perusahaan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('company-bank-accounts.index', filters)" text="Kembali ke Daftar Rekening Bank" />
                        </div>
                        <CompanyBankAccountForm 
                            :bankAccount="bankAccount"
                            :companies="companies" 
                            :accounts="accounts" 
                            :currencies="currencies"
                            :filters="filters" 
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
