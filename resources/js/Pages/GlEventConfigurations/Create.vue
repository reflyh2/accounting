<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import GlEventConfigurationForm from './Partials/GlEventConfigurationForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   branches: Array,
   accounts: Array,
   eventCodes: Array,
});
</script>

<template>
   <Head title="Buat Konfigurasi GL Event" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Konfigurasi GL Event Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('gl-event-configurations.index', filters)" text="Kembali ke Daftar Konfigurasi GL Event" />
                  </div>
                  <GlEventConfigurationForm :companies="companies" :branches="branches" :filters="filters" :accounts="accounts" :eventCodes="eventCodes" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>

