<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetCategoryForm from './Partials/AssetCategoryForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   accounts: Array,
});
</script>

<template>
   <Head title="Buat Kategori Aset" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Kategori Aset Baru</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('asset-categories.index', filters)" text="Kembali ke Daftar Kategori Aset" />
                  </div>
                  <AssetCategoryForm :companies="companies" :accounts="accounts" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template> 