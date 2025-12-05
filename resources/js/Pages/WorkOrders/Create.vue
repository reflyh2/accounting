<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import WorkOrderForm from './Partials/WorkOrderForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   branches: Array,
   boms: Array,
   locations: Array,
});
</script>

<template>
   <Head title="Buat Work Order" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Work Order Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('work-orders.index', filters)" text="Kembali ke Daftar Work Order" />
                  </div>
                  <WorkOrderForm :companies="companies" :branches="branches" :boms="boms" :locations="locations" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
