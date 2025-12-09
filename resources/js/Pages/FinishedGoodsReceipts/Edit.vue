<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import FinishedGoodsReceiptForm from './Partials/FinishedGoodsReceiptForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   finishedGoodsReceipt: Object,
   filters: Object,
   companies: Array,
   branches: Array,
   locations: Array,
   workOrders: Array,
});
</script>

<template>
   <Head title="Ubah Finished Goods Receipt" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Finished Goods Receipt</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('finished-goods-receipts.index', filters)" text="Kembali ke Daftar Finished Goods Receipts" />
                  </div>
                  <FinishedGoodsReceiptForm :finishedGoodsReceipt="finishedGoodsReceipt" :companies="companies" :branches="branches" :locations="locations" :workOrders="workOrders" :filters="filters" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>

