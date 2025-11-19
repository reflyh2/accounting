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
    accounts: Array,
    filters: Object,
    bookData: Array,
    primaryCurrency: Object,
});

const form = ref({
    company_id: props.filters.company_id || [],
    branch_id: props.filters.branch_id || [],
    account_id: props.filters.account_id || [],
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
    router.get(route('cash-bank-book.index'), form.value, {
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
    
    window.open(route('cash-bank-book.download', params));
}

function getJournalViewRoute(journal) {
    const routes = {
        'general': 'journals.show',
        'cash_receipt': 'cash-receipt-journals.show',
        'cash_payment': 'cash-payment-journals.show'
    };

    const routeName = routes[journal.journal_type];
    return route(routeName, journal.id);
}
</script>

<template>
   <Head title="Buku Kas & Bank" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Buku Kas & Bank</h2>
      </template>

      <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
         <AccountingReportTabs activeTab="cash-bank-book.index" />

         <div class="bg-white shadow-sm sm:rounded border border-gray-200">
            <div class="p-6 text-gray-900">
               <!-- Filters -->
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
                        v-model="form.account_id"
                        :options="accounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                        label="Akun"
                        multiple
                        placeholder="Pilih akun"
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

               <!-- Actions -->
               <div class="flex items-center">
                  <AppPrimaryButton @click="generateReport">
                        Tampilkan Laporan
                  </AppPrimaryButton>

                  <AppDropdown
                        v-if="bookData.length > 0"
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

               <!-- Report Content -->
               <div v-if="bookData.length > 0" class="mt-6">
                  <ReportLayout
                     title="Buku Kas & Bank"
                     :dateRange="`${new Date(form.start_date).toLocaleDateString('id-ID')} s/d ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                     :companies="selectedCompanies"
                     :branches="selectedBranches"
                  >
                     <div v-for="data in bookData" :key="`${data.account.id}-${data.currency.id}`" class="mb-8">
                        <h3 class="text-lg font-semibold mb-2">
                           {{ data.account.code }} - {{ data.account.name }} ({{ data.currency.code }})
                        </h3>
                        <ReportTable>
                          <thead>
                            <tr class="bg-gray-100">
                              <ReportTH sticky>Tanggal</ReportTH>
                              <ReportTH sticky>No. Jurnal</ReportTH>
                              <ReportTH sticky>Keterangan</ReportTH>
                              <ReportTH sticky>Masuk</ReportTH>
                              <ReportTH sticky>Keluar</ReportTH>
                              <ReportTH sticky>Saldo</ReportTH>
                              <ReportTH sticky>Kurs</ReportTH>
                              <ReportTH sticky>Perubahan ({{ primaryCurrency.code }})</ReportTH>
                              <ReportTH sticky>Saldo ({{ primaryCurrency.code }})</ReportTH>
                            </tr>
                          </thead>
                          <tbody>
                            <tr class="group">
                              <ReportTD :colspan="5" class="font-semibold">
                                Saldo Awal
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.opening_balance) }}
                              </ReportTD>
                              <ReportTD :colspan="2"></ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.primary_opening_balance) }}
                              </ReportTD>
                            </tr>

                            <tr v-for="mutation in data.mutations" :key="mutation.id" class="group">
                              <ReportTD>
                                {{ new Date(mutation.journal.date).toLocaleDateString('id-ID') }}
                              </ReportTD>
                              <ReportTD>
                                <a :href="getJournalViewRoute(mutation.journal)" target="_blank" class="text-main-500 hover:text-main-800">
                                  {{ mutation.journal.journal_number }}
                                </a>
                              </ReportTD>
                              <ReportTD>
                                {{ mutation.journal.description }}
                              </ReportTD>
                              <ReportTD class="text-right">
                                {{ mutation.debit > 0 ? formatNumber(mutation.debit) : '-' }}
                              </ReportTD>
                              <ReportTD class="text-right">
                                {{ mutation.credit > 0 ? formatNumber(mutation.credit) : '-' }}
                              </ReportTD>
                              <ReportTD class="text-right">
                                {{ formatNumber(data.opening_balance + 
                                  data.mutations
                                    .filter(m => m.id <= mutation.id)
                                    .reduce((sum, m) => {
                                      return data.account.balance_type === 'debit'
                                        ? sum + Number(m.debit) - Number(m.credit)
                                        : sum + Number(m.credit) - Number(m.debit)
                                    }, 0)) }}
                              </ReportTD>
                              <ReportTD class="text-right">
                                {{ formatNumber(mutation.exchange_rate) }}
                              </ReportTD>
                              <ReportTD class="text-right">
                                {{ data.account.balance_type === 'debit' ? formatNumber(mutation.primary_currency_debit - mutation.primary_currency_credit) : formatNumber(mutation.primary_currency_credit - mutation.primary_currency_debit) }}
                              </ReportTD>
                              <ReportTD class="text-right">
                                {{ formatNumber(data.primary_opening_balance + 
                                  data.mutations
                                    .filter(m => m.id <= mutation.id)
                                    .reduce((sum, m) => {
                                      return data.account.balance_type === 'debit'
                                        ? sum + Number(m.primary_currency_debit) - Number(m.primary_currency_credit)
                                        : sum + Number(m.primary_currency_credit) - Number(m.primary_currency_debit)
                                    }, 0)) }}
                              </ReportTD>
                            </tr>

                            <tr class="group">
                              <ReportTD :colspan="3" class="font-semibold">
                                Saldo Akhir
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.mutations.reduce((sum, m) => sum + Number(m.debit), 0)) }}
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.mutations.reduce((sum, m) => sum + Number(m.credit), 0)) }}
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.ending_balance) }}
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.primary_ending_balance / data.ending_balance) }}
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.mutations.reduce((sum, m) => sum + (data.account.balance_type === 'debit' ? (Number(m.primary_currency_debit) - Number(m.primary_currency_credit)) : (Number(m.primary_currency_credit) - Number(m.primary_currency_debit))), 0)) }}
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.primary_ending_balance) }}
                              </ReportTD>
                            </tr>
                            <tr class="group">
                              <ReportTD :colspan="8" class="font-semibold">
                                Saldo Akhir Gabungan {{ data.account.name }}
                              </ReportTD>
                              <ReportTD class="text-right font-semibold">
                                {{ formatNumber(data.combined_primary_ending_balance) }}
                              </ReportTD>
                            </tr>
                          </tbody>
                        </ReportTable>
                     </div>
                  </ReportLayout>
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>