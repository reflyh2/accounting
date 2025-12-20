<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import GoodsReceiptForm from './Partials/GoodsReceiptForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   goodsReceipt: Object,
   purchaseOrders: Array,
   selectedPurchaseOrders: Array,
   selectedPartnerId: Number,
   suppliers: Array,
   locations: Array,
   filters: Object,
});
</script>

<template>
   <Head title="Ubah Penerimaan Pembelian" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Penerimaan Pembelian</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('goods-receipts.index', filters)" text="Kembali ke Daftar Penerimaan" />
                  </div>
                  <GoodsReceiptForm
                     :goodsReceipt="goodsReceipt"
                     :companies="companies"
                     :branches="branches"
                     :purchaseOrders="purchaseOrders"
                     :selectedPurchaseOrders="selectedPurchaseOrders"
                     :selectedPartnerId="selectedPartnerId"
                     :suppliers="suppliers"
                     :locations="locations"
                     :filters="filters"
                  />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
