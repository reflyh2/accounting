<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CashReceiptJournalForm from './Partials/CashReceiptJournalForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   branches: Array,
   accounts: Array,
   kasBankAccounts: Array,
   primaryCurrency: Object,
});
</script>

<template>
   <Head title="Buat Penerimaan Kas" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Penerimaan Kas Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('cash-receipt-journals.index', filters)" text="Kembali ke Daftar Penerimaan Kas" />
                  </div>
                  <CashReceiptJournalForm :companies="companies" :branches="branches" :filters="filters" :accounts="accounts" :kasBankAccounts="kasBankAccounts" :primaryCurrency="primaryCurrency" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>