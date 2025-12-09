<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ref, watch, onMounted, nextTick } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
   journal: Object,
   companies: Array,
   branches: Array,
   accounts: Array,
   filters: Object,
   primaryCurrency: Object,
});

const form = useForm({
   company_id: props.journal?.branch?.branch_group?.company_id || null,
   branch_id: props.journal?.branch_id || null,
   date: props.journal?.date || new Date().toISOString().split('T')[0],
   reference_number: props.journal?.reference_number || '',
   description: props.journal?.description || '',
   entries: props.journal?.journal_entries || [
      { account_id: null, debit: 0, credit: 0, currency_id: null, exchange_rate: 1 },
   ],
   create_another: false,
});

const submitted = ref(false);
const selectedCompany = ref(props.journal?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0].id));
const minDate = ref(props.journal ? new Date(new Date(props.journal.date).getFullYear(), 0, 1).toLocaleDateString('en-CA') : null);
const maxDate = ref(props.journal ? new Date(new Date(props.journal.date).getFullYear(), 11, 31).toLocaleDateString('en-CA') : null);

watch(selectedCompany, (newCompanyId) => {
   if (!props.journal) {
      router.reload({ only: ['branches', 'accounts'], data: { company_id: newCompanyId } });
   }
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.journal && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
   selectedCompany.value = props.journal?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0].id);
   if (!props.journal && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
});

function addEntry() {
   const totalDebit = form.entries.reduce((sum, entry) => sum + (Number(entry.debit) * Number(entry.exchange_rate)), 0);
   const totalCredit = form.entries.reduce((sum, entry) => sum + (Number(entry.credit) * Number(entry.exchange_rate)), 0);
   const difference = Math.abs(totalDebit - totalCredit);
   
   if (totalDebit > totalCredit) {
      form.entries.push({ account_id: '', debit: 0, credit: difference, currency_id: '', exchange_rate: 1 });
   } else {
      form.entries.push({ account_id: '', debit: difference, credit: 0, currency_id: '', exchange_rate: 1 });
   }
}

function removeEntry(index) {
   form.entries.splice(index, 1);
}

function updateSelectedCurrency(index) {
   form.entries[index].currency_id = props.accounts.find(a => a.id === form.entries[index].account_id)?.currencies[0].id || null;
}

function updateRate(index) {
   form.entries[index].exchange_rate = props.accounts.find(a => a.id === form.entries[index].account_id)?.currencies.find(c => c.id === form.entries[index].currency_id)?.company_rates.find(r => r.company_id === selectedCompany.value)?.exchange_rate || 1;
}

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   if (props.journal) {
      form.put(route('journals.update', props.journal.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('journals.store'), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
            if (createAnother) {
               form.reset();
               form.clearErrors();
            }
         },
         onError: () => {
            submitted.value = false;
         }
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
                  label="Perusahaan:"
                  placeholder="Pilih Perusahaan"
                  :error="form.errors.company_id"
                  :disabled="!!props.journal"
                  required
               />
               
               <AppSelect
                  v-model="form.branch_id"
                  :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang:"
                  placeholder="Pilih Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.journal"
                  required
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.date"
                  type="date"
                  label="Tanggal:"
                  :error="form.errors.date"
                  :min="minDate"
                  :max="maxDate"
                  required
               />
               
               <AppInput
                  v-model="form.reference_number"
                  label="Nomor Referensi:"
                  :error="form.errors.reference_number"
               />
            </div>
            <AppTextarea
               v-model="form.description"
               label="Catatan:"
               :error="form.errors.description"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Jurnal</h3>
            <p class="mb-2">Jurnal adalah catatan transaksi yang dicatat dalam sistem akuntansi. Pastikan informasi yang dimasukkan akurat.</p>
            <ul class="list-disc list-inside">
               <li>Pilih perusahaan yang sesuai</li>
               <li>Pilih cabang yang sesuai</li>
               <li>Pilih tanggal jurnal</li>
               <li>Nomor referensi jurnal adalah nomor dokumen dari pihak luar yang menjadi dasar jurnal. Contoh: Faktur, Invoice, Nota, dll.</li>
               <li>Catatan jurnal adalah deskripsi singkat yang menjelaskan jurnal.</li>
               <li>Jurnal dapat memiliki beberapa entri, dan setiap entri harus memiliki akun, debit, kredit, mata uang, dan kurs yang sesuai.</li>
            </ul>
         </div>
      </div>
      
      <div class="overflow-x-auto">
         <h2 class="text-lg font-semibold">Entri Jurnal</h2>
         <p class="text-sm text-gray-500 mb-4">Masukan akun, debit, kredit, mata uang, dan kurs yang sesuai dengan jurnal yang akan dibuat.</p>

         <table class="min-w-full bg-white border border-gray-300">
            <thead>
               <tr class="bg-gray-100">
                  <th class="border border-gray-300 text-sm min-w-48 lg:min-w-72 px-1.5 py-1.5">Akun</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Debet</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Kredit</th>
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
                     <AppInput
                        v-model="entry.credit"
                        :numberFormat="true"
                        :error="form.errors[`entries.${index}.credit`]"
                        :prefix="props.accounts.find(a => a.id === entry.account_id)?.currencies.find(c => c.id === entry.currency_id)?.symbol"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                     <div v-if="entry.currency_id != null && entry.currency_id != props.primaryCurrency.id" class="text-gray-500 text-xs -mt-4">= {{ props.primaryCurrency.symbol + ' ' + formatNumber(entry.credit * entry.exchange_rate) }}</div>
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="entry.currency_id"
                        :maxRows="1"
                        :options="props.accounts.find(a => a.id === entry.account_id)?.currencies.map(currency => ({ value: currency.id, label: currency.code })) || []"
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
                  <th class="border border-gray-300 px-4 py-2 text-right">Total</th>
                  <th class="border border-gray-300 px-4 py-2 text-left">{{ props.primaryCurrency.symbol + ' ' + formatNumber(form.entries.reduce((sum, entry) => sum + (Number(entry.debit) * Number(entry.exchange_rate)), 0)) }}</th>
                  <th class="border border-gray-300 px-4 py-2 text-left">{{ props.primaryCurrency.symbol + ' ' + formatNumber(form.entries.reduce((sum, entry) => sum + (Number(entry.credit) * Number(entry.exchange_rate)), 0)) }}</th>
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
            {{ props.journal ? 'Ubah' : 'Tambah' }} Jurnal
         </AppPrimaryButton>
         <AppUtilityButton v-if="!props.journal" type="button" @click="submitForm(true)" class="mr-2">
            Tambah & Buat Lagi
         </AppUtilityButton>
         <AppSecondaryButton @click="$inertia.visit(route('journals.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>