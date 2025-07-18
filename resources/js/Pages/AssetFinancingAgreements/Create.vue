<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetFinancingAgreementForm from './Partials/AssetFinancingAgreementForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   filters: Object,
   companies: Array,
   branches: Array,
   partners: Array,
   assetInvoices: Array,
   statusOptions: Object,
   paymentFrequencyOptions: Object,
   interestCalculationMethodOptions: Object,
});
</script>

<template>
   <Head title="Buat Perjanjian Pembiayaan Aset" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buat Perjanjian Pembiayaan Aset Baru</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('asset-financing-agreements.index', filters)" text="Kembali ke Daftar Perjanjian Pembiayaan Aset" />
                  </div>
                  <AssetFinancingAgreementForm 
                     :companies="companies"
                     :branches="branches"
                     :partners="partners" 
                     :assetInvoices="assetInvoices" 
                     :filters="filters" 
                     :statusOptions="statusOptions"
                     :paymentFrequencyOptions="paymentFrequencyOptions"
                     :interestCalculationMethodOptions="interestCalculationMethodOptions"
                  />
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template> 