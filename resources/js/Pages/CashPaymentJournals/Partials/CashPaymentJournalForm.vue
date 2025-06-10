<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
   cashPaymentJournal: Object,
   companies: Array,
   branches: Array,
   accounts: Array,
   kasBankAccounts: Array,
   filters: Object,
   primaryCurrency: Object,
});

const form = useForm({
   company_id: props.cashPaymentJournal?.branch?.branch_group?.company_id || null,
   branch_id: props.cashPaymentJournal?.branch_id || (props.branches.length > 1 ? null : (props.branches[0]?.id || null)),
   date: props.cashPaymentJournal?.date || new Date().toISOString().split('T')[0],
   reference_number: props.cashPaymentJournal?.reference_number || '',
   description: props.cashPaymentJournal?.description || '',
   kas_bank_account_id: props.cashPaymentJournal?.journal_entries?.find(entry => entry.credit != 0)?.account_id || null,
   kas_bank_account_currency_id: props.cashPaymentJournal?.journal_entries?.find(entry => entry.credit != 0)?.currency_id || null,
   kas_bank_account_exchange_rate: props.cashPaymentJournal?.journal_entries?.find(entry => entry.credit != 0)?.exchange_rate || 1,
   entries: props.cashPaymentJournal?.journal_entries?.filter(entry => entry.credit == 0) || [
      { account_id: null, debit: 0, currency_id: null, exchange_rate: 1 },
   ],
   create_another: false,
});

const kas_bank_account_amount = computed(() => {
   return form.entries.reduce((sum, entry) => sum + (Number(entry.debit) * Number(entry.exchange_rate)), 0) / form.kas_bank_account_exchange_rate;
});

const submitted = ref(false);
const selectedCompany = ref(props.cashPaymentJournal?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0].id));
const minDate = ref(props.cashPaymentJournal ? new Date(new Date(props.cashPaymentJournal.date).getFullYear(), 0, 1).toLocaleDateString('en-CA') : null);
const maxDate = ref(props.cashPaymentJournal ? new Date(new Date(props.cashPaymentJournal.date).getFullYear(), 11, 31).toLocaleDateString('en-CA') : null);

watch(selectedCompany, (newCompanyId) => {
   router.reload({ only: ['branches', 'accounts', 'kasBankAccounts'], data: { company_id: newCompanyId } });
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.cashPaymentJournal && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
   selectedCompany.value = props.cashPaymentJournal?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0].id);
});

function addEntry() {
   form.entries.push({ account_id: null, debit: 0, currency_id: null, exchange_rate: 1 });
}

function removeEntry(index) {
   form.entries.splice(index, 1);
}

function updateSelectedCurrency(index) {
   const account = props.accounts.find(a => a.id === form.entries[index].account_id);
   form.entries[index].currency_id = account?.currencies?.[0]?.id || null;
}

function updateKasBankRate() {
   const account = props.kasBankAccounts.find(a => a.id === form.kas_bank_account_id);
   const currency = account?.currencies?.find(c => c.id === form.kas_bank_account_currency_id);
   form.kas_bank_account_exchange_rate = currency?.company_rates?.find(r => r.company_id === selectedCompany.value)?.exchange_rate || 1;
}

function updateRate(index) {
   const account = props.accounts.find(a => a.id === form.entries[index].account_id);
   const currency = account?.currencies?.find(c => c.id === form.entries[index].currency_id);
   form.entries[index].exchange_rate = currency?.company_rates?.find(r => r.company_id === selectedCompany.value)?.exchange_rate || 1;
}

function submitForm(createAnother = false) {
   form.create_another = createAnother;
   submitted.value = true;

   if (props.cashPaymentJournal) {
      form.put(route('cash-payment-journals.update', props.cashPaymentJournal.id), {
         preserveScroll: true,
      });
   } else {
      form.post(route('cash-payment-journals.store'), {
         preserveScroll: true,
      });
   }
}
</script>

<template>
   <form @submit.prevent="submitForm(false)" class="space-y-4">
      <div class="flex justify-between">
         <div class="w-2/3 max-w-2xl mr-8">
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="selectedCompany"
                  :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                  label="Perusahaan"
                  :error="form.errors.company_id"
                  :disabled="!!props.cashPaymentJournal"
                  required
               />
               <AppSelect
               v-model="form.branch_id"
               :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.cashPaymentJournal"
                  required
               />
            </div>
            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.date"
                  type="date"
                  label="Tanggal"
                  :error="form.errors.date"
                  :min="minDate"
                  :max="maxDate"
                  required
               />
               <AppInput
                  v-model="form.reference_number"
                  type="text"
                  label="Nomor Referensi"
                  :error="form.errors.reference_number"
               />
            </div>
            <AppTextarea
               v-model="form.description"
               label="Catatan"
               :error="form.errors.description"
            />
            <div class="border border-gray-300 p-4 rounded-lg text-sm">
               <AppSelect
                  v-model="form.kas_bank_account_id"
                  :options="props.kasBankAccounts.map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                  label="Keluar dari Akun"
                  :error="form.errors.kas_bank_account_id"
                  required
               />
               <div class="grid grid-cols-3 gap-4">
                  <div class="col-span-1">
                     <AppSelect
                        v-model="form.kas_bank_account_currency_id"
                        :options="props.kasBankAccounts?.find(a => a.id === form.kas_bank_account_id)?.currencies.map(currency => ({ value: currency.id, label: currency.code }))"
                        label="Mata Uang"
                        :error="form.errors.kas_bank_account_currency_id"
                        required
                        @update:modelValue="updateKasBankRate"
                     />
                  </div>
                  <div class="col-span-2">
                     <AppInput
                        v-model="form.kas_bank_account_exchange_rate"
                        :numberFormat="true"
                        label="Kurs"
                        :error="form.errors.kas_bank_account_exchange_rate"
                        required
                     />
                  </div>
               </div>
               <AppInput
                  v-model="kas_bank_account_amount"
                  :numberFormat="true"
                  label="Jumlah"
                  :error="form.errors.kas_bank_account_amount"
                  :prefix="props.kasBankAccounts?.find(a => a.id === form.kas_bank_account_id)?.currencies.find(c => c.id === form.kas_bank_account_currency_id)?.symbol"
                  :disabled="true"
               />
            </div>
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Jurnal Penerimaan Kas</h3>
            <p class="mb-2">Jurnal Penerimaan Kas adalah catatan transaksi penerimaan kas yang dicatat dalam sistem akuntansi. Pastikan informasi yang dimasukkan akurat.</p>
            <ul class="list-disc list-inside">
               <li>Pilih perusahaan yang sesuai</li>
               <li>Pilih cabang yang sesuai</li>
               <li>Pilih tanggal jurnal</li>
               <li>Nomor referensi jurnal adalah nomor dokumen dari pihak luar yang menjadi dasar jurnal. Contoh: Faktur, Invoice, Nota, dll.</li>
               <li>Catatan jurnal adalah deskripsi singkat yang menjelaskan jurnal.</li>
               <li>Pilih akun Kas/Bank yang sesuai untuk penerimaan kas</li>
               <li>Jurnal dapat memiliki beberapa entri, dan setiap entri harus memiliki akun, jumlah, mata uang, dan kurs yang sesuai.</li>
            </ul>
         </div>
      </div>
      
      <div class="overflow-x-auto">
         <table class="min-w-full bg-white border border-gray-300">
            <thead>
               <tr class="bg-gray-100">
                  <th class="border border-gray-300 text-sm min-w-48 lg:min-w-72 px-1.5 py-1.5">Akun</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Jumlah</th>
                  <th class="border border-gray-300 text-sm px-1.5 py-1.5">Mata Uang</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Kurs</th>
                  <th class="border border-gray-300 px-1.5 py-1.5"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="(entry, index) in form.entries" :key="index">
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="entry.account_id"
                        :options="props.accounts.map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        :error="form.errors[`entries.${index}.account_id`]"
                        :maxRows="3"
                        @update:modelValue="updateSelectedCurrency(index)"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="entry.debit"
                        :numberFormat="true"
                        :error="form.errors[`entries.${index}.debit`]"
                        :prefix="props.accounts.find(a => a.id === entry.account_id)?.currencies.find(c => c.id === entry.currency_id)?.symbol"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                     <div v-if="entry.currency_id != null && entry.currency_id != props.primaryCurrency.id" class="text-gray-500 text-xs -mt-4">= {{ props.primaryCurrency.symbol + ' ' + formatNumber(entry.debit * entry.exchange_rate) }}</div>
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="entry.currency_id"
                        :options="props.accounts.find(a => a.id === entry.account_id)?.currencies.map(currency => ({ value: currency.id, label: currency.code }))"
                        :error="form.errors[`entries.${index}.currency_id`]"
                        @update:modelValue="updateRate(index)"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="entry.exchange_rate"
                        :numberFormat="true"
                        :error="form.errors[`entries.${index}.exchange_rate`]"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                     <button type="button" @click="removeEntry(index)" class="text-red-500 hover:text-red-700">
                        <TrashIcon class="w-5 h-5" />
                     </button>
                  </td>
               </tr>
            </tbody>

            <tfoot>
               <tr class="text-sm">
                  <th class="border border-gray-300 px-1.5 py-1.5 text-right">Total</th>
                  <th class="border border-gray-300 px-1.5 py-1.5 text-left">{{ props.primaryCurrency.symbol + ' ' + formatNumber(form.entries.reduce((sum, entry) => sum + (Number(entry.debit) * Number(entry.exchange_rate)), 0)) }}</th>
                  <th colspan="3"></th>
               </tr>
            </tfoot>
         </table>

         <div class="flex mt-2 mb-4">
            <button type="button" @click="addEntry" class="flex items-center text-main-500 hover:text-main-700">
               <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Entri
            </button>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.cashPaymentJournal ? 'Ubah' : 'Tambah' }} Pengeluaran Kas
         </AppPrimaryButton>
         <AppUtilityButton v-if="!props.cashPaymentJournal" type="button" @click="submitForm(true)" class="mr-2">
            Tambah & Buat Lagi
         </AppUtilityButton>
         <AppSecondaryButton @click="$inertia.visit(route('cash-payment-journals.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>