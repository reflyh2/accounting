<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import TaxCategoryForm from './Partials/TaxCategoryForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   appliesTo: Object,
   behaviors: Object,
});
</script>

<template>
   <Head title="Buat Kategori Pajak" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Kategori Pajak Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('tax-categories.index', filters)" text="Kembali ke Daftar Kategori" />
                  </div>
                  <TaxCategoryForm 
                     :companies="companies" 
                     :appliesTo="appliesTo" 
                     :behaviors="behaviors"
                     :filters="filters" 
                  />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
