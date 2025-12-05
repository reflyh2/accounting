<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PartnerForm from './Partials/PartnerForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   availableRoles: Object,
});
</script>

<template>
   <Head title="Buat Partner Bisnis" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Partner Bisnis Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('partners.index', filters)" text="Kembali ke Daftar Partner" />
                  </div>
                  <PartnerForm :companies="companies" :filters="filters" :availableRoles="availableRoles" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template> 