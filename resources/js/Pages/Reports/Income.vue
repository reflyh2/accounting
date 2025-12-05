<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportLayout from '@/Layouts/ReportLayout.vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
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
    report_type: props.filters.report_type || 'summary',
    start_date: props.filters.start_date || '',
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
    router.get(route('income.index'), form.value, {
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
    
    window.open(route('income.download', params));
}
</script>

<template>
   <Head title="Laba/Rugi" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Laba/Rugi</h2>
      </template>

      <div class="mx-auto">
         <AccountingReportTabs activeTab="income.index" />

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
                     <div class="grid grid-cols-2 gap-4">
                           <AppInput
                              v-model="form.start_date"
                              type="date"
                              label="Dari Tanggal"
                           />
                           <AppInput
                              v-model="form.end_date"
                              type="date"
                              label="Sampai Tanggal"
                           />
                     </div>
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
                        v-if="reportData"
                        :title="`Laba/Rugi`"
                        :dateRange="`${new Date(form.start_date).toLocaleDateString('id-ID')} s/d ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                        :companies="selectedCompanies"
                        :branches="selectedBranches"
                     >
                        <ReportTable>
                           <thead>
                              <tr class="bg-gray-100">
                                 <ReportTH sticky>Keterangan</ReportTH>
                                 <ReportTH sticky>Bulan Lalu</ReportTH>
                                 <ReportTH sticky>Bulan Ini</ReportTH>
                                 <ReportTH sticky>Tahun Berjalan</ReportTH>
                              </tr>
                           </thead>
                           <tbody>
                              <!-- Revenue Section -->
                              <tr class="bg-gray-50 font-semibold group">
                                 <ReportTD colspan="4">PENDAPATAN</ReportTD>
                              </tr>
                              <template v-if="reportData.revenue?.revenue?.accounts?.length">
                                 <template v-for="account in reportData.revenue.revenue.accounts" :key="account.account.id">
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
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.ytd) }}
                                       </ReportTD>
                                    </tr>
                                 </template>
                              </template>

                              <template v-if="reportData.revenue?.cogs?.accounts?.length">
                                 <template v-for="account in reportData.revenue.cogs.accounts" :key="account.account.id">
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
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.ytd) }}
                                       </ReportTD>
                                    </tr>
                                 </template>
                              </template>

                              <tr class="bg-gray-50 font-semibold group">
                                 <ReportTD>Laba Kotor</ReportTD>
                                 <ReportTD class="text-right">
                                    {{ formatNumber(reportData.revenue.gross_profit.previous) }}
                                 </ReportTD>
                                 <ReportTD class="text-right">
                                    {{ formatNumber(reportData.revenue.gross_profit.current) }}
                                 </ReportTD>
                                 <ReportTD class="text-right">
                                    {{ formatNumber(reportData.revenue.gross_profit.ytd) }}
                                 </ReportTD>
                              </tr>

                              <!-- Cash Costs Section -->
                              <tr class="bg-gray-50 font-semibold group">
                                 <ReportTD colspan="4">BIAYA KAS</ReportTD>
                              </tr>
                              <template v-if="reportData.cash_costs?.operational">
                                 <tr class="font-semibold group">
                                    <ReportTD colspan="4" class="pl-8">Biaya Operasional:</ReportTD>
                                 </tr>
                                 <template v-for="account in reportData.cash_costs.operational.accounts" :key="account.account.id">
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
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.ytd) }}
                                       </ReportTD>
                                    </tr>
                                 </template>
                                 <tr class="font-semibold group" v-if="reportData.cash_costs.operational.total">
                                    <ReportTD class="pl-8">Total Biaya Kas Operasional</ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.cash_costs.operational.total.previous) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.cash_costs.operational.total.current) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.cash_costs.operational.total.ytd) }}
                                    </ReportTD>
                                 </tr>
                              </template>
                              <template v-if="reportData.cash_costs?.non_operational">
                                 <tr class="font-semibold group">
                                    <ReportTD colspan="4" class="pl-8">Biaya (Pendapatan) Non-operasional:</ReportTD>
                                 </tr>
                                 <template v-for="account in reportData.cash_costs.non_operational.other_income" :key="account.account.id">
                                    <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                       <ReportTD :class="'pl-' + ((account.account.level * 4) + 12)">
                                          {{ account.account.code }} - {{ account.account.name }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous ? -account.balances.previous : 0) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current ? -account.balances.current : 0) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.ytd ? -account.balances.ytd : 0) }}
                                       </ReportTD>
                                    </tr>
                                 </template>
                                 <template v-for="account in reportData.cash_costs.non_operational.other_expenses" :key="account.account.id">
                                    <tr :class="[{ 'font-semibold': filters.report_type === 'detailed' && account.account.is_parent }, 'group']">
                                       <ReportTD :class="'pl-' + ((account.account.level * 4) + 12)">
                                          {{ account.account.code }} - {{ account.account.name }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.previous) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.current) }}
                                       </ReportTD>
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.ytd) }}
                                       </ReportTD>
                                    </tr>
                                 </template>
                                 <tr class="font-semibold group" v-if="reportData.cash_costs.non_operational.total">
                                    <ReportTD class="pl-8">Total Biaya Kas Non-operasional</ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.cash_costs.non_operational.total.previous) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.cash_costs.non_operational.total.current) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.cash_costs.non_operational.total.ytd) }}
                                    </ReportTD>
                                 </tr>
                              </template>
                              <tr class="bg-gray-50 font-semibold group" v-if="reportData.cash_costs.total">
                                 <ReportTD>Total Biaya Kas</ReportTD>
                                 <ReportTD class="text-right">
                                    {{ formatNumber(reportData.cash_costs.total.previous) }}
                                 </ReportTD>
                                 <ReportTD class="text-right">
                                    {{ formatNumber(reportData.cash_costs.total.current) }}
                                 </ReportTD>
                                 <ReportTD class="text-right">
                                    {{ formatNumber(reportData.cash_costs.total.ytd) }}
                                 </ReportTD>
                              </tr>

                              <!-- Non-cash Costs Section -->
                              <template v-if="reportData.non_cash_costs">
                                 <tr class="bg-gray-50 font-semibold group">
                                    <ReportTD colspan="4">BIAYA NON-KAS</ReportTD>
                                 </tr>
                                 <template v-for="account in reportData.non_cash_costs.depreciation" :key="account.account.id">
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
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.ytd) }}
                                       </ReportTD>
                                    </tr>
                                 </template>
                                 <template v-for="account in reportData.non_cash_costs.amortization" :key="account.account.id">
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
                                       <ReportTD class="text-right">
                                          {{ filters.report_type === 'detailed' && account.account.is_parent ? '' : formatNumber(account.balances.ytd) }}
                                       </ReportTD>
                                    </tr>
                                 </template>
                                 <tr class="bg-gray-50 font-semibold group" v-if="reportData.non_cash_costs.total">
                                    <ReportTD>Total Biaya Non-kas</ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.non_cash_costs.total.previous) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.non_cash_costs.total.current) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.non_cash_costs.total.ytd) }}
                                    </ReportTD>
                                 </tr>
                              </template>

                              <!-- Summary Section -->
                              <template v-if="reportData.total_cost && reportData.net_profit">
                                 <tr class="bg-gray-50 font-semibold group">
                                    <ReportTD>Total Biaya</ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.total_cost.previous) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.total_cost.current) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.total_cost.ytd) }}
                                    </ReportTD>
                                 </tr>
                                 <tr class="bg-gray-50 font-semibold group">
                                    <ReportTD>Laba Bersih</ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.net_profit.previous) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.net_profit.current) }}
                                    </ReportTD>
                                    <ReportTD class="text-right">
                                       {{ formatNumber(reportData.net_profit.ytd) }}
                                    </ReportTD>
                                 </tr>
                              </template>
                           </tbody>
                        </ReportTable>
                     </ReportLayout>
                  </div>
               </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>