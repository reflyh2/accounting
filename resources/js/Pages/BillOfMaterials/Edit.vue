<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import BillOfMaterialForm from './Partials/BillOfMaterialForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   bom: Object,
   filters: Object,
   companies: Array,
   branches: Array,
   finishedProducts: Array,
   componentProducts: Array,
});
</script>

<template>
   <Head title="Ubah Bill of Materials" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Bill of Materials</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('bill-of-materials.index', filters)" text="Kembali ke Daftar BOM" />
                  </div>
                  <BillOfMaterialForm :bom="bom" :companies="companies" :branches="branches" :finishedProducts="finishedProducts" :componentProducts="componentProducts" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
