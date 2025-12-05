<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import JournalForm from './Partials/JournalForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   journal: Object,
   filters: Object,
   companies: Array,
   branches: Array,
   accounts: Array,
   primaryCurrency: Object,
});
</script>

<template>
   <Head title="Ubah Jurnal" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Jurnal</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('journals.index', filters)" text="Kembali ke Daftar Jurnal" />
                  </div>
                  <JournalForm :journal="journal" :companies="companies" :branches="branches" :accounts="accounts" :filters="filters" :primaryCurrency="primaryCurrency" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>