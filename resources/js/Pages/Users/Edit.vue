<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import UserForm from './Partials/UserForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   user: Object,
   roles: Array,
   branches: Array,
   companies: Array,
   filters: Object,
});
</script>

<template>
   <Head title="Ubah Pengguna" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Pengguna</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('users.index', filters)" text="Kembali ke Daftar Pengguna" />
                  </div>
                  <UserForm :user="user" :roles="roles" :branches="branches" :companies="companies" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>