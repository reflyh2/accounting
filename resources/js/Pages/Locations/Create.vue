<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import LocationForm from './Partials/LocationForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
defineProps({
   branches: Array,
   filters: Object,
});
</script>

<template>
   <Head title="Buat Lokasi" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Lokasi</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('locations.index', filters)" text="Kembali ke Daftar Lokasi" />
                  </div>
                  <LocationForm :branches="branches" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
