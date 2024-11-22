<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportLayout from '@/Layouts/ReportLayout.vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import TabLinks from '@/Components/TabLinks.vue';
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
   report_type: props.filters.report_type || 'summary',
   end_date: props.filters.end_date || '',
});

const tabs = [
   { label: 'Buku Besar', route: 'general-ledger.index', active: false },
   { label: 'Buku Kas & Bank', route: 'cash-bank-book.index', active: false },
   { label: 'Laba/Rugi', route: 'income.index', active: false },
   { label: 'Neraca', route: 'balance-sheet.index', active: true },
];

const selectedCompanies = computed(() => {
   if (!form.value.company_id?.length) return [];
   return props.companies.filter(c => form.value.company_id.includes(c.id));
});

const selectedBranches = computed(() => {
   if (!form.value.branch_id?.length) return [];
   return props.branches.filter(b => form.value.branch_id.includes(b.id));
});

function generateReport() {
   router.get(route('balance-sheet.index'), form.value, {
      preserveState: true,
      preserveScroll: true,
   });
}

function formatNumber(number) {
   return new Intl.NumberFormat('id-ID').format(number);
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
   
   window.open(route('balance-sheet.download', params));
}
</script>

<template>
   <Head title="Neraca" />

   <AuthenticatedLayout>
      <template #header>
         <h2 class="font-semibold text-xl text-gray-800 leading-tight">Neraca</h2>
      </template>

      <div class="min-w-min md:min-w-max mx-auto">
         <TabLinks :tabs="tabs" />

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
                     <AppSelect
                           v-model="form.report_type"
                           :options="[
                              { value: 'summary', label: 'Ringkasan' },
                              { value: 'detailed', label: 'Detail' }
                           ]"
                           label="Tipe Laporan"
                           placeholder="Pilih tipe laporan"
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
                           :title="`Neraca`"
                           :dateRange="`Per ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                           :companies="selectedCompanies"
                           :branches="selectedBranches"
                     >
                           <ReportTable>
                              <thead>
                                 <tr class="bg-gray-100">
                                       <ReportTH sticky>Keterangan</ReportTH>
                                       <ReportTH sticky class="text-right">Bulan Lalu</ReportTH>
                                       <ReportTH sticky class="text-right">Bulan Ini</ReportTH>
                                 </tr>
                              </thead>
                              <tbody>
                                 <!-- Assets Section -->
                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD :colspan="3">AKTIVA</ReportTD>
                                 </tr>
                                 
                                 <!-- Cash & Bank -->
                                 <template v-for="account in reportData.assets.cash_bank" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 4)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Fixed Assets -->
                                 <template v-for="account in reportData.assets.fixed_asset" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 4)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Accumulated Depreciation -->
                                 <template v-for="account in reportData.assets.accumulated_depreciation" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 4)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Other Assets -->
                                 <template v-for="account in reportData.assets.other_asset" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 4)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Total Assets -->
                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD>Total Aktiva</ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.total_assets.previous) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.total_assets.current) }}
                                       </ReportTD>
                                 </tr>

                                 <tr class="bg-gray-50 group">
                                    <ReportTD :colspan="3">&nbsp;</ReportTD>
                                 </tr>

                                 <!-- Liabilities Section -->
                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD :colspan="3">PASIVA</ReportTD>
                                 </tr>

                                 <tr class="font-semibold group">
                                       <ReportTD :colspan="3" class="pl-8">Kewajiban</ReportTD>
                                 </tr>

                                 <!-- Accounts Payable -->
                                 <template v-for="account in reportData.liabilities.payable" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 8)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Other Payables -->
                                 <template v-for="account in reportData.liabilities.other_payable" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 8)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Short-term Liabilities -->
                                 <template v-for="account in reportData.liabilities.short_term" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 8)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Long-term Liabilities -->
                                 <template v-for="account in reportData.liabilities.long_term" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 8)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Total Liabilities -->
                                 <tr class="font-semibold group">
                                       <ReportTD class="pl-8">Total Kewajiban</ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.liabilities.total.previous) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.liabilities.total.current) }}
                                       </ReportTD>
                                 </tr>

                                 <tr class="font-semibold group">
                                       <ReportTD :colspan="3" class="pl-8">Modal</ReportTD>
                                 </tr>

                                 <!-- Equity Section -->
                                 <template v-for="account in reportData.equity.accounts" :key="account.account.id">
                                       <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                          <ReportTD :class="'pl-' + ((account.account.level * 4) + 8)">
                                             {{ account.account.code }} - {{ account.account.name }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                          </ReportTD>
                                          <ReportTD class="text-right">
                                             {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                          </ReportTD>
                                       </tr>
                                 </template>

                                 <!-- Total Equity -->
                                 <tr class="font-semibold group">
                                       <ReportTD class="pl-8">Total Modal</ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.equity.total.previous) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.equity.total.current) }}
                                       </ReportTD>
                                 </tr>

                                 <!-- Total Liabilities & Equity -->
                                 <tr class="bg-gray-50 font-semibold group">
                                       <ReportTD>Total Pasiva</ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.total_liabilities_equity.previous) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ formatNumber(reportData.total_liabilities_equity.current) }}
                                       </ReportTD>
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
