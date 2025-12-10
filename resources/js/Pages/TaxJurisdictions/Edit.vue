<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import TaxJurisdictionForm from './Partials/TaxJurisdictionForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   jurisdiction: Object,
   filters: Object,
   parentJurisdictions: Array,
   levels: Object,
});
</script>

<template>
   <Head title="Ubah Yurisdiksi Pajak" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Yurisdiksi Pajak</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('tax-jurisdictions.index', filters)" text="Kembali ke Daftar Yurisdiksi" />
                  </div>
                  <TaxJurisdictionForm :jurisdiction="jurisdiction" :parentJurisdictions="parentJurisdictions" :levels="levels" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
