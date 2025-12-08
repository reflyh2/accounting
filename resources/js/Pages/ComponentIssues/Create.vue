<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import ComponentIssueForm from './Partials/ComponentIssueForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   branches: Array,
   locations: Array,
   workOrders: Array,
});
</script>

<template>
   <Head title="Buat Component Issue" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Component Issue Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('component-issues.index', filters)" text="Kembali ke Daftar Component Issues" />
                  </div>
                  <ComponentIssueForm :companies="companies" :branches="branches" :locations="locations" :workOrders="workOrders" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>

