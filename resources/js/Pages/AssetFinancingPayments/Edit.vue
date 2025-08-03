<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetFinancingPaymentForm from './Partials/AssetFinancingPaymentForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
    payment: Object,
    companies: Array,
    branches: Array,
    currencies: Array,
    creditors: Array,
    agreements: Array,
});
</script>

<template>
   <Head title="Ubah Pembayaran Pembiayaan Aset" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Pembayaran Pembiayaan Aset</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('asset-financing-payments.index')" text="Kembali ke Daftar Pembayaran" />
                  </div>
                  <AssetFinancingPaymentForm :payment="payment" :companies="companies" :branches="branches" :currencies="currencies" :creditors="creditors" :agreements="agreements" />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template> 