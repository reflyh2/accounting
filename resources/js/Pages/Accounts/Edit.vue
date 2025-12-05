<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AccountForm from './Partials/AccountForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   account: Object,
   companies: Array,
   currencies: Array,
   parentAccounts: Array,
   filters: Object,
   allAccounts: Array,
});
</script>

<template>
   <Head title="Ubah Akun" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Akun</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('accounts.index', filters)" text="Kembali ke Daftar Akun" />
                  </div>
                  <AccountForm :account="account" :companies="companies" :currencies="currencies" :parentAccounts="parentAccounts" :filters="filters" :allAccounts="allAccounts" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>