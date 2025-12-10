<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import TaxComponentForm from './Partials/TaxComponentForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   component: Object,
   filters: Object,
   jurisdictions: Array,
   kinds: Object,
   cascadeModes: Object,
   deductibleModes: Object,
});
</script>

<template>
   <Head title="Ubah Komponen Pajak" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Komponen Pajak</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('tax-components.index', filters)" text="Kembali ke Daftar Komponen" />
                  </div>
                  <TaxComponentForm 
                     :component="component" 
                     :jurisdictions="jurisdictions" 
                     :kinds="kinds" 
                     :cascadeModes="cascadeModes"
                     :deductibleModes="deductibleModes"
                     :filters="filters" 
                  />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
