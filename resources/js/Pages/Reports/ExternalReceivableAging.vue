<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportLayout from '@/Layouts/ReportLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AccountingReportTabs from '@/Tabs/AccountingReportTabs.vue';
import AppDropdown from '@/Components/AppDropdown.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import ReportTable from '@/Components/ReportTable.vue';
import ReportTH from '@/Components/ReportTH.vue';
import ReportTD from '@/Components/ReportTD.vue';

const props = defineProps({
   companies: Array,
   branches: Array,
   filters: Object,
   reportData: Object,
});

const form = ref({
   company_id: props.filters.company_id || [],
   branch_id: props.filters.branch_id || [],
   end_date: props.filters.end_date || '',
});

const selectedCompanies = computed(() => {
   if (!form.value.company_id?.length) return [];
   return props.companies.filter(c => form.value.company_id.includes(c.id));
});

const selectedBranches = computed(() => {
   if (!form.value.branch_id?.length) return [];
   return props.branches.filter(b => form.value.branch_id.includes(b.id));
});

function generateReport() {
   router.get(route('external-receivable-aging.index'), form.value, {
      preserveState: true,
      preserveScroll: true,
   });
}

function formatNumber(number) {
   return new Intl.NumberFormat('id-ID').format(number || 0);
}

const downloadOptions = [
   { format: 'xlsx', label: 'Download Excel' },
   { format: 'pdf', label: 'Download PDF' },
];

function downloadReport(format) {
   const params = {
      ...form.value,
      format,
      download: true
   };
   window.open(route('external-receivable-aging.download', params));
}
</script>

<template>
   <Head title="Umur Piutang" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Umur Piutang</h2>
      </template>

      <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
         <AccountingReportTabs activeTab="external-receivable-aging.index" />

         <div class="bg-white shadow-sm sm:rounded border border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="grid grid-cols-2 gap-4 mb-4">
                     <AppSelect
                           v-model="form.company_id"
                           :options="companies.map(company => ({ value: company.id, label: company.name }))"
                           label="Perusahaan"
                           multiple
                           placeholder="Pilih perusahaan"
                     />
                     <AppSelect
                           v-model="form.branch_id"
                           :options="branches.map(branch => ({ value: branch.id, label: branch.name }))"
                           label="Cabang"
                           multiple
                           placeholder="Pilih cabang"
                     />
                     <AppInput
                           v-model="form.end_date"
                           type="date"
                           label="Per Tanggal"
                     />
                  </div>

                  <div class="flex items-center">
                     <AppPrimaryButton @click="generateReport">
                           Tampilkan Laporan
                     </AppPrimaryButton>

                     <AppDropdown
                           v-if="reportData"
                           :items="downloadOptions"
                           @select="downloadReport($event.format)"
                           class="ml-2"
                     >  
                           <AppUtilityButton>
                              <ArrowDownTrayIcon class="w-5 h-5 mr-1" />
                              Download
                           </AppUtilityButton>
                     </AppDropdown>
                  </div>

                  <div v-if="reportData" class="mt-6">
                     <ReportLayout 
                           :title="`Umur Piutang`"
                           :dateRange="`Per ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                           :companies="selectedCompanies"
                           :branches="selectedBranches"
                     >
                           <ReportTable>
                              <thead>
                                 <tr class="bg-gray-100">
                                       <ReportTH sticky>Partner</ReportTH>
                                       <ReportTH sticky class="text-right">Belum Jatuh Tempo</ReportTH>
                                       <ReportTH sticky class="text-right">1-30 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">31-60 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">61-90 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">91+ Hari</ReportTH>
                                       <ReportTH sticky class="text-right">Total</ReportTH>
                                 </tr>
                              </thead>
                              <tbody>
                                 <template v-for="row in reportData.rows" :key="row.partner.id">
                                       <tr class="group">
                                          <ReportTD>{{ row.partner.name }}</ReportTD>
                                          <ReportTD class="text-right">{{ formatNumber(row.not_yet_due) }}</ReportTD>
                                          <ReportTD class="text-right">{{ formatNumber(row.days_1_30) }}</ReportTD>
                                          <ReportTD class="text-right">{{ formatNumber(row.days_31_60) }}</ReportTD>
                                          <ReportTD class="text-right">{{ formatNumber(row.days_61_90) }}</ReportTD>
                                          <ReportTD class="text-right">{{ formatNumber(row.days_91_plus) }}</ReportTD>
                                          <ReportTD class="text-right">{{ formatNumber(row.total) }}</ReportTD>
                                       </tr>
                                 </template>

                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD>Total</ReportTD>
                                       <ReportTD class="text-right">{{ formatNumber(reportData.totals.not_yet_due) }}</ReportTD>
                                       <ReportTD class="text-right">{{ formatNumber(reportData.totals.days_1_30) }}</ReportTD>
                                       <ReportTD class="text-right">{{ formatNumber(reportData.totals.days_31_60) }}</ReportTD>
                                       <ReportTD class="text-right">{{ formatNumber(reportData.totals.days_61_90) }}</ReportTD>
                                       <ReportTD class="text-right">{{ formatNumber(reportData.totals.days_91_plus) }}</ReportTD>
                                       <ReportTD class="text-right">{{ formatNumber(reportData.totals.total) }}</ReportTD>
                                 </tr>
                              </tbody>
                           </ReportTable>
                     </ReportLayout>
                  </div>
               </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>


