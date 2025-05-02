<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PartnerForm from './Partials/PartnerForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   partner: Object,
   filters: Object,
   companies: Array,
   availableRoles: Object,
});
</script>

<template>
   <Head title="Ubah Partner Bisnis" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Partner Bisnis</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('partners.index', filters)" text="Kembali ke Daftar Partner" />
                  </div>
                  <PartnerForm :partner="partner" :companies="companies" :filters="filters" :availableRoles="availableRoles" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template> 