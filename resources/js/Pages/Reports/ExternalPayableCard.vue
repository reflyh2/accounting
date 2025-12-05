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
  partners: Array,
  filters: Object,
  cardData: Array,
});

const form = ref({
  company_id: props.filters.company_id || [],
  branch_id: props.filters.branch_id || [],
  partner_id: props.filters.partner_id || [],
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
  router.get(route('external-payable-card.index'), form.value, {
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
  window.open(route('external-payable-card.download', params));
}
</script>

<template>
  <Head title="Kartu Hutang" />

  <AuthenticatedLayout>
    <template #header>
      <h2>Kartu Hutang</h2>
    </template>

    <div class="mx-auto">
      <AccountingReportTabs activeTab="external-payable-card.index" />

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
              v-model="form.partner_id"
              :options="partners.map(partner => ({ value: partner.id, label: partner.name }))"
              label="Partner"
              multiple
              placeholder="Pilih partner"
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
              v-if="cardData?.length"
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

          <div v-if="cardData?.length" class="mt-6">
            <ReportLayout 
              :title="`Kartu Hutang`"
              :dateRange="`${new Date(form.start_date).toLocaleDateString('id-ID')} s/d ${new Date(form.end_date).toLocaleDateString('id-ID')}`"
              :companies="selectedCompanies"
              :branches="selectedBranches"
            >
              <div v-for="card in cardData" :key="card.partner.id" class="mb-8">
                <h3 class="text-lg font-semibold mb-2">{{ card.partner.name }}</h3>
                <ReportTable>
                  <thead>
                    <tr class="bg-gray-100">
                      <ReportTH sticky>Tanggal</ReportTH>
                      <ReportTH sticky>Dokumen</ReportTH>
                      <ReportTH sticky class="text-right">Penambahan</ReportTH>
                      <ReportTH sticky class="text-right">Pembayaran</ReportTH>
                      <ReportTH sticky class="text-right">Saldo</ReportTH>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in card.rows" :key="row.date + '-' + row.balance" class="group">
                      <ReportTD>
                        {{ new Date(row.date).toLocaleDateString('id-ID') }}
                        <span v-if="row.is_opening" class="ml-1 text-gray-500">(Saldo Awal)</span>
                      </ReportTD>
                      <ReportTD>
                        <template v-if="!row.is_opening && row.doc_number">
                          <a
                            v-if="row.doc_type === 'addition'"
                            :href="route('external-payables.show', row.doc_id)"
                            target="_blank"
                            class="text-main-500 hover:text-main-800"
                          >
                            {{ row.doc_number }}
                          </a>
                          <a
                            v-else-if="row.doc_type === 'payment'"
                            :href="route('external-payable-payments.show', row.doc_id)"
                            target="_blank"
                            class="text-main-500 hover:text-main-800"
                          >
                            {{ row.doc_number }}
                          </a>
                          <span v-else>{{ row.doc_number }}</span>
                        </template>
                        <template v-else>-</template>
                      </ReportTD>
                      <ReportTD class="text-right">
                        {{ row.addition > 0 ? formatNumber(row.addition) : '-' }}
                      </ReportTD>
                      <ReportTD class="text-right">
                        {{ row.payment > 0 ? formatNumber(row.payment) : '-' }}
                      </ReportTD>
                      <ReportTD class="text-right">
                        {{ formatNumber(row.balance) }}
                      </ReportTD>
                    </tr>
                    <tr class="bg-gray-50 font-semibold group" v-if="card.totals">
                      <ReportTD>Total</ReportTD>
                      <ReportTD>-</ReportTD>
                      <ReportTD class="text-right">
                        {{ formatNumber(card.totals.additions || 0) }}
                      </ReportTD>
                      <ReportTD class="text-right">
                        {{ formatNumber(card.totals.payments || 0) }}
                      </ReportTD>
                      <ReportTD class="text-right">
                        {{ formatNumber(card.totals.ending_balance || 0) }}
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


