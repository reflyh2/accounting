<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import FinishedGoodsReceiptForm from './Partials/FinishedGoodsReceiptForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   branches: Array,
   locations: Array,
   workOrders: Array,
});
</script>

<template>
   <Head title="Buat Finished Goods Receipt" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Finished Goods Receipt Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('finished-goods-receipts.index', filters)" text="Kembali ke Daftar Finished Goods Receipts" />
                  </div>
                  <FinishedGoodsReceiptForm :companies="companies" :branches="branches" :locations="locations" :workOrders="workOrders" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>

