<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import BranchForm from './Partials/BranchForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
defineProps({
   branchGroups: Array,
   companies: Array,
   filters: Object,
});
</script>

<template>
   <Head title="Buat Cabang" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Cabang</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('branches.index', filters)" text="Kembali ke Daftar Cabang" />
                  </div>
                  <BranchForm :branchGroups="branchGroups" :companies="companies" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>