<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AssetFinancingAgreementForm from './Partials/AssetFinancingAgreementForm.vue';
import AppBackLink from '@/Components/AppBackLink.vue';

defineProps({
   agreement: Object,
   filters: Object,
   companies: Array,
   branches: Array,
   partners: Array,
   currencies: Array,
   assetInvoices: Array,
   statusOptions: Object,
   paymentFrequencyOptions: Object,
   interestCalculationMethodOptions: Object,
});
</script>

<template>
   <Head title="Ubah Perjanjian Pembiayaan Aset" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Ubah Data Perjanjian Pembiayaan Aset</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('asset-financing-agreements.index', filters)" text="Kembali ke Daftar Perjanjian Pembiayaan Aset" />
                  </div>
                  <AssetFinancingAgreementForm 
                     :agreement="agreement"
                     :companies="companies"
                     :branches="branches"
                     :partners="partners" 
                     :currencies="currencies"
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