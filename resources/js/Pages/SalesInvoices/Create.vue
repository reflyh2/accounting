<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import SalesInvoiceForm from './Partials/SalesInvoiceForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   salesOrder: Object,
   defaultLines: Array,
   selectedSalesOrderLabel: String,
   salesOrderSearchUrl: String,
   primaryCurrency: Object,
});
</script>

<template>
   <Head title="Buat Faktur Penjualan" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Faktur Penjualan Baru</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('sales-invoices.index', filters)" text="Kembali ke Daftar Faktur Penjualan" />
                  </div>
                  <SalesInvoiceForm :salesOrder="salesOrder" :defaultLines="defaultLines" :filters="filters" :selectedSalesOrderLabel="selectedSalesOrderLabel" :salesOrderSearchUrl="salesOrderSearchUrl" :primaryCurrency="primaryCurrency" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
