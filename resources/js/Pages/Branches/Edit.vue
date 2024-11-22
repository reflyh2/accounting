<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import BranchForm from './Partials/BranchForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   branch: Object,
   branchGroups: Array,
   companies: Array,
   filters: Object,
});
</script>

<template>
   <Head title="Ubah Cabang" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Cabang</h2>
      </template>

      <div>
         <div class="min-w-min md:min-w-max mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('branches.index', filters)" text="Kembali ke Daftar Cabang" />
                  </div>
                  <BranchForm :branch="branch" :branchGroups="branchGroups" :companies="companies" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>