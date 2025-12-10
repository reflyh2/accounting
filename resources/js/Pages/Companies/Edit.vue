<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CompanyForm from './Partials/CompanyForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
defineProps({
   company: Object,
   filters: Object,
   taxJurisdictions: Array,
});
</script>

<template>
   <Head title="Ubah Perusahaan" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Perusahaan</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('companies.index', filters)" text="Kembali ke Daftar Perusahaan" />
                  </div>
                  <CompanyForm :company="company" :filters="filters" :tax-jurisdictions="taxJurisdictions" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>