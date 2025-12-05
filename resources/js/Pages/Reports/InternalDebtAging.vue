<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppDropdown from '@/Components/AppDropdown.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import ReportLayout from '@/Layouts/ReportLayout.vue';
import ReportTable from '@/Components/ReportTable.vue';
import ReportTH from '@/Components/ReportTH.vue';
import ReportTD from '@/Components/ReportTD.vue';
import InternalDebtTabs from '@/Tabs/InternalDebtTabs.vue';

const props = defineProps({
   companies: Array,
   branches: Array,
   filters: Object,
   reportData: Object, // { payables, receivables, combined } or null
});

const form = ref({
   company_id: props.filters.company_id || null,
   branch_id: props.filters.branch_id || null,
   end_date: props.filters.end_date || '',
});

const selectedCompanies = computed(() => {
   if (!form.value.company_id) return [];
   const c = props.companies.find(c => c.id === form.value.company_id);
   return c ? [c] : [];
});

const selectedBranches = computed(() => {
   if (!form.value.branch_id) return [];
   const b = props.branches.find(b => b.id === form.value.branch_id);
   return b ? [b] : [];
});

function generateReport() {
   router.get(route('internal-debt-aging.index'), form.value, {
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
   window.open(route('internal-debt-aging.download', params));
}
</script>

<template>
   <Head title="Umur Hutang/Piutang Internal" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Umur Hutang/Piutang Internal</h2>
      </template>

      <div class="mx-auto">
         <InternalDebtTabs activeTab="internal-debt-aging.index" />

         <div class="bg-white shadow-sm sm:rounded border border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="grid grid-cols-2 gap-4 mb-4">
                     <AppSelect
                           v-model="form.company_id"
                           :options="companies.map(company => ({ value: company.id, label: company.name }))"
                           label="Perusahaan"
                           placeholder="Pilih perusahaan"
                     />
                     <AppSelect
                           v-model="form.branch_id"
                           :options="branches.map(branch => ({ value: branch.id, label: branch.name }))"
                           label="Cabang (wajib)"
                           placeholder="Pilih cabang"
                     />
                     <AppInput
                           v-model="form.end_date"
                           type="date"
                           label="Per Tanggal"
                     />
                  </div>

                  <div class="flex items-center">
                     <AppPrimaryButton :disabled="!form.branch_id" @click="generateReport">
                           Tampilkan Laporan
                     </AppPrimaryButton>

                     <AppDropdown
                           v-if="reportData"
                           :items="downloadOptions"
                           @select="downloadReport($event.format)"
                           class="ml-2"
                     >  
                           <AppUtilityButton :disabled="!form.branch_id">
                              <ArrowDownTrayIcon class="w-5 h-5 mr-1" />
                              Download
                           </AppUtilityButton>
                     </AppDropdown>
                  </div>

                  <div v-if="reportData" class="mt-6 space-y-8">
                     <!-- Receivables (first, green) -->
                     <ReportLayout 
                           :title="`Piutang Internal`"
                           :dateRange="`Per ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                           :companies="selectedCompanies"
                           :branches="selectedBranches"
                     >
                           <ReportTable>
                              <thead>
                                 <tr class="bg-gray-100">
                                       <ReportTH sticky>Cabang</ReportTH>
                                       <ReportTH sticky class="text-right">Belum Jatuh Tempo</ReportTH>
                                       <ReportTH sticky class="text-right">1-30 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">31-60 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">61-90 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">91+ Hari</ReportTH>
                                       <ReportTH sticky class="text-right">Total</ReportTH>
                                 </tr>
                              </thead>
                              <tbody>
                                 <template v-for="row in reportData.receivables.rows" :key="row.branch.id">
                                       <tr class="group">
                                          <ReportTD>{{ (row.branch.branch_group?.company?.name ? row.branch.branch_group.company.name + ' - ' : '') + row.branch.name }}</ReportTD>
                                          <ReportTD class="text-right text-green-600">{{ formatNumber(row.not_yet_due) }}</ReportTD>
                                          <ReportTD class="text-right text-green-600">{{ formatNumber(row.days_1_30) }}</ReportTD>
                                          <ReportTD class="text-right text-green-600">{{ formatNumber(row.days_31_60) }}</ReportTD>
                                          <ReportTD class="text-right text-green-600">{{ formatNumber(row.days_61_90) }}</ReportTD>
                                          <ReportTD class="text-right text-green-600">{{ formatNumber(row.days_91_plus) }}</ReportTD>
                                          <ReportTD class="text-right text-green-600">{{ formatNumber(row.total) }}</ReportTD>
                                       </tr>
                                 </template>

                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD>Total</ReportTD>
                                       <ReportTD class="text-right text-green-600">{{ formatNumber(reportData.receivables.totals.not_yet_due) }}</ReportTD>
                                       <ReportTD class="text-right text-green-600">{{ formatNumber(reportData.receivables.totals.days_1_30) }}</ReportTD>
                                       <ReportTD class="text-right text-green-600">{{ formatNumber(reportData.receivables.totals.days_31_60) }}</ReportTD>
                                       <ReportTD class="text-right text-green-600">{{ formatNumber(reportData.receivables.totals.days_61_90) }}</ReportTD>
                                       <ReportTD class="text-right text-green-600">{{ formatNumber(reportData.receivables.totals.days_91_plus) }}</ReportTD>
                                       <ReportTD class="text-right text-green-600">{{ formatNumber(reportData.receivables.totals.total) }}</ReportTD>
                                 </tr>
                              </tbody>
                           </ReportTable>
                     </ReportLayout>

                     <!-- Payables (second, red) -->
                     <ReportLayout 
                           :title="`Hutang Internal`"
                           :dateRange="`Per ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                           :companies="selectedCompanies"
                           :branches="selectedBranches"
                     >
                           <ReportTable>
                              <thead>
                                 <tr class="bg-gray-100">
                                       <ReportTH sticky>Cabang</ReportTH>
                                       <ReportTH sticky class="text-right">Belum Jatuh Tempo</ReportTH>
                                       <ReportTH sticky class="text-right">1-30 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">31-60 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">61-90 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">91+ Hari</ReportTH>
                                       <ReportTH sticky class="text-right">Total</ReportTH>
                                 </tr>
                              </thead>
                              <tbody>
                                 <template v-for="row in reportData.payables.rows" :key="row.branch.id">
                                       <tr class="group">
                                          <ReportTD>{{ (row.branch.branch_group?.company?.name ? row.branch.branch_group.company.name + ' - ' : '') + row.branch.name }}</ReportTD>
                                          <ReportTD class="text-right text-red-600">{{ formatNumber(row.not_yet_due) }}</ReportTD>
                                          <ReportTD class="text-right text-red-600">{{ formatNumber(row.days_1_30) }}</ReportTD>
                                          <ReportTD class="text-right text-red-600">{{ formatNumber(row.days_31_60) }}</ReportTD>
                                          <ReportTD class="text-right text-red-600">{{ formatNumber(row.days_61_90) }}</ReportTD>
                                          <ReportTD class="text-right text-red-600">{{ formatNumber(row.days_91_plus) }}</ReportTD>
                                          <ReportTD class="text-right text-red-600">{{ formatNumber(row.total) }}</ReportTD>
                                       </tr>
                                 </template>

                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD>Total</ReportTD>
                                       <ReportTD class="text-right text-red-600">{{ formatNumber(reportData.payables.totals.not_yet_due) }}</ReportTD>
                                       <ReportTD class="text-right text-red-600">{{ formatNumber(reportData.payables.totals.days_1_30) }}</ReportTD>
                                       <ReportTD class="text-right text-red-600">{{ formatNumber(reportData.payables.totals.days_31_60) }}</ReportTD>
                                       <ReportTD class="text-right text-red-600">{{ formatNumber(reportData.payables.totals.days_61_90) }}</ReportTD>
                                       <ReportTD class="text-right text-red-600">{{ formatNumber(reportData.payables.totals.days_91_plus) }}</ReportTD>
                                       <ReportTD class="text-right text-red-600">{{ formatNumber(reportData.payables.totals.total) }}</ReportTD>
                                 </tr>
                              </tbody>
                           </ReportTable>
                     </ReportLayout>

                     <!-- Combined -->
                     <ReportLayout 
                           :title="`Gabungan (Piutang - Hutang)`"
                           :dateRange="`Per ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                           :companies="selectedCompanies"
                           :branches="selectedBranches"
                     >
                           <ReportTable>
                              <thead>
                                 <tr class="bg-gray-100">
                                       <ReportTH sticky>Cabang</ReportTH>
                                       <ReportTH sticky class="text-right">Belum Jatuh Tempo</ReportTH>
                                       <ReportTH sticky class="text-right">1-30 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">31-60 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">61-90 Hari</ReportTH>
                                       <ReportTH sticky class="text-right">91+ Hari</ReportTH>
                                       <ReportTH sticky class="text-right">Total</ReportTH>
                                 </tr>
                              </thead>
                              <tbody>
                                 <template v-for="row in reportData.combined.rows" :key="row.branch.id">
                                       <tr class="group">
                                          <ReportTD>{{ (row.branch.branch_group?.company?.name ? row.branch.branch_group.company.name + ' - ' : '') + row.branch.name }}</ReportTD>
                                          <ReportTD class="text-right" :class="(row.not_yet_due||0) > 0 ? 'text-green-600' : ((row.not_yet_due||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(row.not_yet_due) }}</ReportTD>
                                          <ReportTD class="text-right" :class="(row.days_1_30||0) > 0 ? 'text-green-600' : ((row.days_1_30||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(row.days_1_30) }}</ReportTD>
                                          <ReportTD class="text-right" :class="(row.days_31_60||0) > 0 ? 'text-green-600' : ((row.days_31_60||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(row.days_31_60) }}</ReportTD>
                                          <ReportTD class="text-right" :class="(row.days_61_90||0) > 0 ? 'text-green-600' : ((row.days_61_90||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(row.days_61_90) }}</ReportTD>
                                          <ReportTD class="text-right" :class="(row.days_91_plus||0) > 0 ? 'text-green-600' : ((row.days_91_plus||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(row.days_91_plus) }}</ReportTD>
                                          <ReportTD class="text-right" :class="(row.total||0) > 0 ? 'text-green-600' : ((row.total||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(row.total) }}</ReportTD>
                                       </tr>
                                 </template>

                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD>Total</ReportTD>
                                       <ReportTD class="text-right" :class="(reportData.combined.totals.not_yet_due||0) > 0 ? 'text-green-600' : ((reportData.combined.totals.not_yet_due||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(reportData.combined.totals.not_yet_due) }}</ReportTD>
                                       <ReportTD class="text-right" :class="(reportData.combined.totals.days_1_30||0) > 0 ? 'text-green-600' : ((reportData.combined.totals.days_1_30||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(reportData.combined.totals.days_1_30) }}</ReportTD>
                                       <ReportTD class="text-right" :class="(reportData.combined.totals.days_31_60||0) > 0 ? 'text-green-600' : ((reportData.combined.totals.days_31_60||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(reportData.combined.totals.days_31_60) }}</ReportTD>
                                       <ReportTD class="text-right" :class="(reportData.combined.totals.days_61_90||0) > 0 ? 'text-green-600' : ((reportData.combined.totals.days_61_90||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(reportData.combined.totals.days_61_90) }}</ReportTD>
                                       <ReportTD class="text-right" :class="(reportData.combined.totals.days_91_plus||0) > 0 ? 'text-green-600' : ((reportData.combined.totals.days_91_plus||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(reportData.combined.totals.days_91_plus) }}</ReportTD>
                                       <ReportTD class="text-right" :class="(reportData.combined.totals.total||0) > 0 ? 'text-green-600' : ((reportData.combined.totals.total||0) < 0 ? 'text-red-600' : '')">{{ formatNumber(reportData.combined.totals.total) }}</ReportTD>
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


