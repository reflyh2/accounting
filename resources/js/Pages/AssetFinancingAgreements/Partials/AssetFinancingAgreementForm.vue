<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { formatNumber } from '@/utils/numberFormat';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';

const page = usePage();

const props = defineProps({
   agreement: Object,
   companies: Array,
   branches: Array,
   partners: Array,
   currencies: Array,
   assetInvoices: Array,
   filters: Object,
   statusOptions: Object,
   paymentFrequencyOptions: Object,
   interestCalculationMethodOptions: Object,
});

const form = useForm({
   company_id: props.agreement?.branch?.branch_group?.company_id || null,
   branch_id: props.agreement?.branch_id || null,
   agreement_date: props.agreement?.agreement_date || new Date().toISOString().split('T')[0],
   creditor_id: props.agreement?.creditor_id || null,
   currency_id: props.agreement?.currency_id || null,
   exchange_rate: props.agreement?.exchange_rate || 1,
   asset_invoice_id: props.agreement?.asset_invoice_id || null,
   total_amount: props.agreement?.total_amount || 0,
   interest_rate: props.agreement?.interest_rate || 0,
   interest_calculation_method: props.agreement?.interest_calculation_method || 'annuity',
   start_date: props.agreement?.start_date || new Date().toISOString().split('T')[0],
   end_date: props.agreement?.end_date || '',
   payment_frequency: props.agreement?.payment_frequency || 'monthly',
   status: props.agreement?.status || 'active',
   notes: props.agreement?.notes || '',
});

const submitted = ref(false);
const selectedCompany = ref(props.agreement?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));

const companyOptions = computed(() => 
   props.companies.map(company => ({ value: company.id, label: company.name }))
);

const branchOptions = computed(() => 
   props.branches?.map(branch => ({ value: branch.id, label: branch.name })) || []
);

const partnerOptions = computed(() => 
   props.partners.map(partner => ({ value: partner.id, label: partner.name }))
);

// Computed currency options
const currencyOptions = computed(() => {
    return props.currencies.map(currency => ({
        value: currency.id,
        label: `${currency.code} - ${currency.name}`
    }));
});

// Computed current currency symbol
const currentCurrencySymbol = computed(() => {
    const currency = props.currencies.find(c => c.id == form.currency_id);
    return currency?.symbol || page.props.primaryCurrency?.symbol || '';
});

const assetInvoiceOptions = computed(() => 
   props.assetInvoices.map(invoice => {
      // Get the first asset from the invoice details, or fallback
      const firstAsset = invoice.asset_invoice_details?.[0]?.asset;
      const assetName = firstAsset?.name || 'No Asset';
      const outstandingAmount = formatNumber(invoice.outstanding_amount || 0);
      
      return {
         value: invoice.id,
         label: `${invoice.number} - ${assetName} (${invoice.partner?.name}) - Outstanding: ${outstandingAmount}`,
         invoice: invoice
      };
   })
);

const statusSelectOptions = computed(() => 
   Object.entries(props.statusOptions).map(([value, label]) => ({ value, label }))
);

const paymentFrequencySelectOptions = computed(() => 
   Object.entries(props.paymentFrequencyOptions).map(([value, label]) => ({ value, label }))
);

const interestCalculationMethodSelectOptions = computed(() => 
   Object.entries(props.interestCalculationMethodOptions).map(([value, label]) => ({ value, label }))
);

const partnerUrl = computed(() => {
    return route('api.partners', { company_id: selectedCompany.value, roles: ['creditor'] });
});

const partnerTableHeaders = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'actions', label: '' }
];

const partnerName = ref(props.agreement?.creditor?.name || '');

watch(selectedCompany, (newCompanyId) => {
   if (!props.agreement) {
      form.branch_id = null;
      form.creditor_id = null;
      form.asset_invoice_id = null;
      form.total_amount = 0;
   }
   router.reload({ only: ['branches', 'partners', 'currencies'], data: { company_id: newCompanyId } });
}, { immediate: true });

watch(() => form.branch_id, (newBranchId) => {
   if (!props.agreement && newBranchId) {
      form.asset_invoice_id = null;
      form.total_amount = 0;
      router.reload({ only: ['assetInvoices'], data: { 
         company_id: selectedCompany.value,
         branch_id: newBranchId,
         currency_id: form.currency_id
      } });
   }
});

watch(() => form.currency_id, (newCurrencyId) => {
   if (!props.agreement && newCurrencyId) {
      router.reload({ only: ['assetInvoices'], data: { 
         company_id: selectedCompany.value,
         branch_id: form.branch_id,
         currency_id: newCurrencyId
      } });
   }
});

// Watch asset invoice selection to auto-calculate total amount
watch(() => form.asset_invoice_id, (newInvoiceId) => {
   if (newInvoiceId && !props.agreement) {
      const selectedInvoice = props.assetInvoices.find(invoice => invoice.id == newInvoiceId);
      if (selectedInvoice && selectedInvoice.outstanding_amount) {
         form.total_amount = selectedInvoice.outstanding_amount;
      }
   }
});

watch(
   () => props.branches,
   (newBranches) => {
      if (!props.agreement && newBranches && newBranches.length === 1) {
         form.branch_id = newBranches[0].id;
      }
   },
   { immediate: true, deep: true }
);

function updateExchangeRate() {
    if (!form.currency_id || !selectedCompany.value) {
        form.exchange_rate = 1;
        return;
    }
    
    const currency = props.currencies.find(c => c.id == form.currency_id);
    if (currency && currency.company_rates) {
        const companyRate = currency.company_rates.find(rate => rate.company_id == selectedCompany.value);
        if (companyRate) {
            form.exchange_rate = companyRate.exchange_rate;
        }
    }
}

// Watch currency selection to update exchange rate
watch(() => form.currency_id, () => {
    updateExchangeRate();
});

onMounted(() => {
   selectedCompany.value = props.agreement?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
   if (!props.agreement && props.branches && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
});

function submitForm() {
   submitted.value = true;
   if (props.agreement) {
      form.put(route('asset-financing-agreements.update', props.agreement.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('asset-financing-agreements.store'), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   }
}
</script>

<template>
   <form @submit.prevent="submitForm" class="space-y-4">
      <div class="flex justify-between">
         <div class="w-2/3 max-w-2xl mr-8">
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="selectedCompany"
                  :options="companyOptions"
                  label="Perusahaan:"
                  placeholder="Pilih Perusahaan"
                  :error="form.errors.company_id"
                  :disabled="!!props.agreement"
                  required
               />
               <AppSelect
                  v-model="form.branch_id"
                  :options="branchOptions"
                  label="Cabang:"
                  placeholder="Pilih Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.agreement"
                  required
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.agreement_date"
                  type="date"
                  label="Tanggal Perjanjian:"
                  :error="form.errors.agreement_date"
                  required
               />
               
               <AppPopoverSearch
                    v-model="form.creditor_id"
                    label="Kreditor:"
                    placeholder="Pilih Kreditor"
                    :url="partnerUrl"
                    valueKey="id"
                    :displayKeys="['name']"
                    :tableHeaders="partnerTableHeaders"
                    :initialDisplayValue="partnerName"
                    :error="form.errors.creditor_id"
                    :modalTitle="'Pilih Kreditor'"
                    :disabled="!selectedCompany"
                    required
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.currency_id"
                  :options="currencyOptions"
                  label="Mata Uang:"
                  placeholder="Pilih Mata Uang"
                  :error="form.errors.currency_id"
                  required
               />
               
               <AppInput
                  v-model="form.exchange_rate"
                  :numberFormat="true"
                  label="Nilai Tukar:"
                  :error="form.errors.exchange_rate"
                  required
               />
            </div>
            
            <div class="grid grid-cols-1 gap-4">
               <AppSelect
                  v-model="form.asset_invoice_id"
                  :options="assetInvoiceOptions"
                  label="Invoice Aset:"
                  placeholder="Pilih Invoice Aset"
                  :error="form.errors.asset_invoice_id"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.total_amount"
                  :numberFormat="true"
                  :prefix="currentCurrencySymbol"
                  label="Total Jumlah:"
                  :error="form.errors.total_amount"
                  required
               />
               
               <AppInput
                  v-model="form.interest_rate"
                  :numberFormat="true"
                  label="Bunga Tahunan (%):"
                  :error="form.errors.interest_rate"
                  suffix="%"
                  required
               />
            </div>

            <div class="grid grid-cols-1 gap-4">
               <AppSelect
                  v-model="form.interest_calculation_method"
                  :options="interestCalculationMethodSelectOptions"
                  label="Metode Perhitungan Bunga:"
                  placeholder="Pilih Metode"
                  :error="form.errors.interest_calculation_method"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.start_date"
                  type="date"
                  label="Tanggal Mulai:"
                  :error="form.errors.start_date"
                  required
               />
               
               <AppInput
                  v-model="form.end_date"
                  type="date"
                  label="Tanggal Selesai:"
                  :error="form.errors.end_date"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.payment_frequency"
                  :options="paymentFrequencySelectOptions"
                  label="Frekuensi Pembayaran:"
                  placeholder="Pilih Frekuensi Pembayaran"
                  :error="form.errors.payment_frequency"
                  required
               />
               
               <AppSelect
                  v-model="form.status"
                  :options="statusSelectOptions"
                  label="Status:"
                  placeholder="Pilih Status"
                  :error="form.errors.status"
                  required
               />
            </div>

            <AppTextarea
               v-model="form.notes"
               label="Catatan:"
               :error="form.errors.notes"
               placeholder="Catatan tambahan..."
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Perjanjian Pembiayaan</h3>
            <p class="mb-2">Perjanjian pembiayaan aset adalah kontrak antara perusahaan dengan kreditor untuk pembiayaan pembelian aset.</p>
            <ul class="list-disc list-inside space-y-1">
               <li>Pilih perusahaan dan cabang terkait</li>
               <li>Pilih kreditor yang akan memberikan pembiayaan</li>
               <li>Pilih invoice aset yang akan dibiayai (hanya yang milik cabang yang dipilih)</li>
               <li>Tentukan total jumlah pembiayaan</li>
               <li>Tentukan suku bunga dalam persentase</li>
               <li>Tentukan periode pembiayaan</li>
               <li>Pilih frekuensi pembayaran (bulanan, kuartalan, atau tahunan)</li>
               <li>Tentukan status perjanjian</li>
               <li>Tambahkan catatan jika diperlukan</li>
            </ul>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2" :disabled="submitted">
            {{ props.agreement ? 'Ubah' : 'Tambah' }} Perjanjian Pembiayaan
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('asset-financing-agreements.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template> 