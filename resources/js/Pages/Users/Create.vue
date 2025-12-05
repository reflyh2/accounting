<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import UserForm from './Partials/UserForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   roles: Array,
   branches: Array,
   companies: Array,
   filters: Object,
});
</script>

<template>
   <Head title="Buat Pengguna" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Pengguna Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('users.index', filters)" text="Kembali ke Daftar Pengguna" />
                  </div>
                  <UserForm :roles="roles" :branches="branches" :companies="companies" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>