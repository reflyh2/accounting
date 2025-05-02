<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetForm from './Partials/AssetForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   asset: Object,
   filters: Object,
   companies: Array,
   branches: Array,
   categories: Array,
   assetTypes: Object,
   acquisitionTypes: Object,
   depreciationMethods: Object,
   statusOptions: Object,
});
</script>

<template>
   <Head title="Edit Aset" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Edit Aset</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('assets.index', filters)" text="Kembali ke Daftar Aset" />
                  </div>
                  <AssetForm 
                    :asset="asset" 
                    :companies="companies" 
                    :branches="branches" 
                    :categories="categories" 
                    :filters="filters" 
                    :assetTypes="assetTypes"
                    :acquisitionTypes="acquisitionTypes"
                    :depreciationMethods="depreciationMethods"
                    :statusOptions="statusOptions"
                  />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template> 