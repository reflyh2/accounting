<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import RoleForm from './Partials/RoleForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
defineProps({
   role: Object,
   filters: Object,
});
</script>

<template>
   <Head title="Ubah Hak Akses" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Hak Akses</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('roles.index', filters)" text="Kembali ke Daftar Hak Akses" />
                  </div>
                  <RoleForm :role="role" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>