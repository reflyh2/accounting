<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import CompanyForm from './Partials/CompanyForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
});
</script>

<template>
   <Head title="Buat Perusahaan" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Perusahaan Baru</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('companies.index', filters)" text="Kembali ke Daftar Perusahaan" />
                  </div>
                  <CompanyForm :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>