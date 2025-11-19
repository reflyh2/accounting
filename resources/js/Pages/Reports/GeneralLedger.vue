<script setup>
import { ref, computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportLayout from '@/Layouts/ReportLayout.vue';
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppInput from '@/Components/AppInput.vue';
import AppDropdown from '@/Components/AppDropdown.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import ReportTable from '@/Components/ReportTable.vue';
import ReportTH from '@/Components/ReportTH.vue';
import ReportTD from '@/Components/ReportTD.vue';
import AccountingReportTabs from '@/Tabs/AccountingReportTabs.vue';

const props = defineProps({
    companies: Array,
    branches: Array,
    accounts: Array,
    filters: Object,
    ledgerData: Array,
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
    router.get(route('general-ledger.index'), form.value, {
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
    
    window.open(route('general-ledger.download', params));
}

function getJournalViewRoute(journal) {
    const routes = {
        'general': 'journals.show',
        'cash_receipt': 'cash-receipt-journals.show',
        'cash_payment': 'cash-payment-journals.show',
        'retained_earnings': 'journals.show',
        'asset_purchase': 'journals.show',
        'asset_financing_payment': 'journals.show',
        'asset_rental_payment': 'journals.show',
        'asset_depreciation': 'journals.show',
        'asset_amortization': 'journals.show',
    };

    let routeName = 'journals.show';
    if (routes[journal.journal_type]) {
        routeName = routes[journal.journal_type];
    }
    
    return route(routeName, journal.id);
}
</script>

<template>
    <Head title="Buku Besar" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buku Besar</h2>
        </template>

         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <AccountingReportTabs activeTab="general-ledger.index" />

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
                  
                  <div class="flex items-center">
                     <AppPrimaryButton @click="generateReport">
                        Tampilkan Laporan
                     </AppPrimaryButton>

                     <AppDropdown
                      v-if="ledgerData.length > 0"
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

                  <div v-if="ledgerData.length > 0" class="mt-6">
                     <ReportLayout
                        :title="`Buku Besar`"
                        :dateRange="`${new Date(form.start_date).toLocaleDateString('id-ID')} s/d ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
                        :companies="selectedCompanies"
                        :branches="selectedBranches"
                     >
                        <div v-for="data in ledgerData" :key="data.account.id" class="mb-8">
                           <h3 class="text-lg font-semibold mb-2">{{ data.account.code }} - {{ data.account.name }}</h3>
                           <ReportTable>
                              <thead>
                                <tr class="bg-gray-100">
                                  <ReportTH sticky>Tanggal</ReportTH>
                                  <ReportTH sticky>No. Jurnal</ReportTH>
                                  <ReportTH sticky>Keterangan</ReportTH>
                                  <ReportTH sticky>Debet</ReportTH>
                                  <ReportTH sticky>Kredit</ReportTH>
                                  <ReportTH sticky>Saldo</ReportTH>
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
                                    {{ mutation.primary_currency_debit > 0 ? formatNumber(mutation.primary_currency_debit) : '-' }}
                                  </ReportTD>
                                  <ReportTD class="text-right">
                                    {{ mutation.primary_currency_credit > 0 ? formatNumber(mutation.primary_currency_credit) : '-' }}
                                  </ReportTD>
                                  <ReportTD class="text-right">
                                    {{ 
                                        (() => {
                                            try {
                                                return formatNumber((data.opening_balance || 0) + 
                                                    data.mutations
                                                        .filter(m => m && m.id <= mutation.id)
                                                        .reduce((sum, m) => {
                                                            if (!m) return sum;
                                                            return data.account.balance_type === 'debit'
                                                                ? sum + Number(m.primary_currency_debit || 0) - Number(m.primary_currency_credit || 0)
                                                                : sum + Number(m.primary_currency_credit || 0) - Number(m.primary_currency_debit || 0)
                                                        }, 0))
                                            } catch (error) {
                                                console.error('Error calculating balance:', error, data);
                                                return '0';
                                            }
                                        })()
                                    }}
                                  </ReportTD>
                                </tr>
                                <tr class="group">
                                  <ReportTD :colspan="3" class="font-semibold">
                                    Saldo Akhir
                                  </ReportTD>
                                  <ReportTD class="text-right font-semibold">
                                    {{ formatNumber(data.mutations.reduce((sum, m) => sum + Number(m?.primary_currency_debit || 0), 0)) }}
                                  </ReportTD>
                                  <ReportTD class="text-right font-semibold">
                                    {{ formatNumber(data.mutations.reduce((sum, m) => sum + Number(m?.primary_currency_credit || 0), 0)) }}
                                  </ReportTD>
                                  <ReportTD class="text-right font-semibold">
                                    {{ formatNumber(data.ending_balance || 0) }}
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