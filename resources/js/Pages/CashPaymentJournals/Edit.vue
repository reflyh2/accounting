<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CashPaymentJournalForm from './Partials/CashPaymentJournalForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   cashPaymentJournal: Object,
   filters: Object,
   companies: Array,
   branches: Array,
   accounts: Array,
   kasBankAccounts: Array,
   primaryCurrency: Object,
});
</script>

<template>
   <Head title="Ubah Pengeluaran Kas" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Pengeluaran Kas</h2>
      </template>

      <div>
         <div class="min-w-max mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('cash-payment-journals.index', filters)" text="Kembali ke Daftar Pengeluaran Kas" />
                  </div>
                  <CashPaymentJournalForm :cashPaymentJournal="cashPaymentJournal" :companies="companies" :branches="branches" :filters="filters" :accounts="accounts" :kasBankAccounts="kasBankAccounts" :primaryCurrency="primaryCurrency" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>