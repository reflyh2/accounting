<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import TaxRuleForm from './Partials/TaxRuleForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   rule: Object,
   filters: Object,
   categories: Array,
   jurisdictions: Array,
   components: Array,
   uoms: Array,
   rateTypes: Object,
});
</script>

<template>
   <Head title="Ubah Aturan Pajak" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Aturan Pajak</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('tax-rules.index', filters)" text="Kembali ke Daftar Aturan" />
                  </div>
                  <TaxRuleForm 
                     :rule="rule" 
                     :categories="categories" 
                     :jurisdictions="jurisdictions" 
                     :components="components"
                     :uoms="uoms"
                     :rateTypes="rateTypes"
                     :filters="filters" 
                  />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
