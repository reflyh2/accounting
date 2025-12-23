<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import SalesReturnForm from './Partials/SalesReturnForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   branches: Array,
   customers: Array,
   salesDeliveries: Array,
   selectedCompanyId: Number,
   selectedBranchId: Number,
   selectedCustomerId: Number,
   selectedSalesDelivery: Object,
   reasonOptions: Array,
});
</script>

<template>
   <Head title="Buat Retur Penjualan" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Retur Penjualan Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('sales-returns.index', filters)" text="Kembali ke Daftar Retur Penjualan" />
                  </div>
                  <SalesReturnForm 
                     :filters="filters" 
                     :companies="companies" 
                     :branches="branches" 
                     :customers="customers" 
                     :salesDeliveries="salesDeliveries" 
                     :selectedCompanyId="selectedCompanyId" 
                     :selectedBranchId="selectedBranchId" 
                     :selectedCustomerId="selectedCustomerId"
                     :selectedSalesDelivery="selectedSalesDelivery" 
                     :reasonOptions="reasonOptions" 
                  />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
