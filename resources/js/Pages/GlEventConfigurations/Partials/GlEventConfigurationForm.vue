<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
   configuration: Object,
   companies: Array,
   branches: Array,
   accounts: Array,
   filters: Object,
   eventCodes: Array,
});

const form = useForm({
   company_id: props.configuration?.company_id || null,
   branch_id: props.configuration?.branch_id || null,
   event_code: props.configuration?.event_code || '',
   is_active: props.configuration?.is_active ?? true,
   description: props.configuration?.description || '',
   lines: props.configuration?.lines || [
      { role: '', direction: 'debit', account_id: null },
      { role: '', direction: 'credit', account_id: null },
   ],
   create_another: false,
});

const selectedCompany = ref(props.configuration?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));

// All possible accounting roles (must match GlEventConfigurationSeeder)
const allRoles = [
   'inventory',
   'grn_clearing',
   'payable',
   'receivable',
   'revenue',
   'cogs',
   'purchase_price_variance',
   'tax_payable',
   'tax_receivable',
   'shipping_charge',
   'shipping_charge_credit',
   'shipping_charge_revenue',
   'shipping_charge_receivable',
   'wip',
   'raw_material',
   'finished_goods',
   'variance',
   'inventory_variance',
   'clearing',
];

// Map event codes to their default role configurations (must match GlEventConfigurationSeeder)
const eventCodeRoleMapping = {
   'purchase.grn_posted': [
      { role: 'inventory', direction: 'debit' },
      { role: 'grn_clearing', direction: 'credit' },
   ],
   'purchase.grn_reversed': [
      { role: 'grn_clearing', direction: 'debit' },
      { role: 'inventory', direction: 'credit' },
   ],
   'purchase.ap_posted': [
      { role: 'grn_clearing', direction: 'debit' },
      { role: 'purchase_price_variance', direction: 'debit' },
      { role: 'tax_receivable', direction: 'debit' },
      { role: 'payable', direction: 'credit' },
   ],
   'purchase.ap_reversed': [
      { role: 'payable', direction: 'debit' },
      { role: 'grn_clearing', direction: 'credit' },
      { role: 'purchase_price_variance', direction: 'credit' },
      { role: 'tax_receivable', direction: 'credit' },
   ],
   'purchase.return_posted': [
      { role: 'payable', direction: 'debit' },
      { role: 'inventory', direction: 'credit' },
   ],
   'purchase.return_reversed': [
      { role: 'inventory', direction: 'debit' },
      { role: 'payable', direction: 'credit' },
   ],
   'sales.delivery_posted': [
      { role: 'cogs', direction: 'debit' },
      { role: 'inventory', direction: 'credit' },
      { role: 'shipping_charge', direction: 'debit' },
      { role: 'shipping_charge_credit', direction: 'credit' },
   ],
   'sales.delivery_reversed': [
      { role: 'inventory', direction: 'debit' },
      { role: 'cogs', direction: 'credit' },
      { role: 'shipping_charge', direction: 'credit' },
      { role: 'shipping_charge_credit', direction: 'debit' },
   ],
   'sales.return_posted': [
      { role: 'inventory', direction: 'debit' },
      { role: 'cogs', direction: 'credit' },
   ],
   'sales.ar_posted': [
      { role: 'receivable', direction: 'debit' },
      { role: 'revenue', direction: 'credit' },
      { role: 'tax_payable', direction: 'credit' },
      { role: 'shipping_charge_receivable', direction: 'debit' },
      { role: 'shipping_charge_revenue', direction: 'credit' },
   ],
   'sales.ar_reversed': [
      { role: 'revenue', direction: 'debit' },
      { role: 'tax_payable', direction: 'debit' },
      { role: 'receivable', direction: 'credit' },
      { role: 'shipping_charge_revenue', direction: 'debit' },
      { role: 'shipping_charge_receivable', direction: 'credit' },
   ],
   'mfg.issue_posted': [
      { role: 'wip', direction: 'debit' },
      { role: 'raw_material', direction: 'credit' },
   ],
   'mfg.receipt_posted': [
      { role: 'finished_goods', direction: 'debit' },
      { role: 'wip', direction: 'credit' },
   ],
   'mfg.variance_posted': [
      { role: 'variance', direction: 'debit' },
      { role: 'wip', direction: 'credit' },
   ],
   'inventory.adjustment_posted': [
      { role: 'inventory', direction: 'debit' },
      { role: 'inventory_variance', direction: 'credit' },
   ],
   'inventory.adjustment_reversed': [
      { role: 'inventory_variance', direction: 'debit' },
      { role: 'inventory', direction: 'credit' },
   ],
   'costing.cogs_recognized': [
      { role: 'cogs', direction: 'debit' },
      { role: 'inventory', direction: 'credit' },
   ],
   'costing.cost_allocated': [
      { role: 'inventory', direction: 'debit' },
      { role: 'clearing', direction: 'credit' },
   ],
};

// Convert roles array to select options
const roleOptions = computed(() => {
   return allRoles.map(role => ({
      value: role,
      label: role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
   }));
});

// Watch for event code changes and auto-populate roles
watch(() => form.event_code, (newEventCode) => {
   if (newEventCode && eventCodeRoleMapping[newEventCode] && !props.configuration) {
      // Auto-populate lines based on event code
      form.lines = eventCodeRoleMapping[newEventCode].map(mapping => ({
         role: mapping.role,
         direction: mapping.direction,
         account_id: null,
      }));
   }
}, { immediate: false });

watch(selectedCompany, (newCompanyId) => {
   form.company_id = newCompanyId;
   if (!props.configuration && newCompanyId) {
      router.reload({ only: ['branches', 'accounts'], data: { company_id: newCompanyId } });
   }
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.configuration && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
   selectedCompany.value = props.configuration?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
   if (!props.configuration && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
   
   // Auto-populate if event code is already selected and not editing
   if (form.event_code && eventCodeRoleMapping[form.event_code] && !props.configuration) {
      form.lines = eventCodeRoleMapping[form.event_code].map(mapping => ({
         role: mapping.role,
         direction: mapping.direction,
         account_id: null,
      }));
   }
});

function addLine() {
   form.lines.push({ role: '', direction: 'debit', account_id: null });
}

function removeLine(index) {
   form.lines.splice(index, 1);
}

function submitForm(createAnother = false) {
   form.create_another = createAnother;
   if (props.configuration) {
      form.put(route('gl-event-configurations.update', props.configuration.id), {
         preserveScroll: true,
         onError: () => {
            // Handle errors
         }
      });
   } else {
      form.post(route('gl-event-configurations.store'), {
         preserveScroll: true,
         onSuccess: () => {
            if (createAnother) {
               form.reset();
               form.clearErrors();
            }
         },
         onError: () => {
            // Handle errors
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
                  placeholder="Pilih Perusahaan (Opsional)"
                  :error="form.errors.company_id"
                  :disabled="!!props.configuration"
               />
               
               <AppSelect
                  v-model="form.branch_id"
                  :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang:"
                  placeholder="Pilih Cabang (Opsional)"
                  :error="form.errors.branch_id"
                  :disabled="!!props.configuration"
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.event_code"
                  :options="props.eventCodes"
                  label="Event Code:"
                  placeholder="Pilih Event Code"
                  :error="form.errors.event_code"
                  :disabled="!!props.configuration"
                  required
               />
               
               <div class="flex items-center">
                  <AppCheckbox
                     v-model="form.is_active"
                     label="Aktif"
                     :error="form.errors.is_active"
                  />
               </div>
            </div>
            
            <AppTextarea
               v-model="form.description"
               label="Deskripsi:"
               :error="form.errors.description"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Konfigurasi</h3>
            <p class="mb-2">Konfigurasi GL Event digunakan untuk memetakan event akuntansi ke akun buku besar.</p>
            <ul class="list-disc list-inside">
               <li>Pilih perusahaan dan cabang (opsional untuk konfigurasi global)</li>
               <li>Pilih event code yang akan dikonfigurasi</li>
               <li>Role akan otomatis terisi berdasarkan event code yang dipilih</li>
               <li>Anda dapat menambah atau mengubah entri sesuai kebutuhan</li>
               <li>Setiap entri harus memiliki role, direction (debit/kredit), dan akun</li>
               <li>Pastikan ada setidaknya satu entri debit dan satu entri kredit</li>
            </ul>
         </div>
      </div>
      
      <div class="overflow-x-auto">
         <table class="min-w-full bg-white border border-gray-300">
            <thead>
               <tr class="bg-gray-100">
                  <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Role</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Direction</th>
                  <th class="border border-gray-300 text-sm min-w-48 lg:min-w-72 px-1.5 py-1.5">Akun</th>
                  <th class="border border-gray-300 px-1.5 py-1.5"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="(line, index) in form.lines" :key="index">
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.role"
                        :options="roleOptions"
                        :error="form.errors[`lines.${index}.role`]"
                        placeholder="Pilih Role"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.direction"
                        :options="[
                           { value: 'debit', label: 'Debit' },
                           { value: 'credit', label: 'Kredit' }
                        ]"
                        :error="form.errors[`lines.${index}.direction`]"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.account_id"
                        :options="props.accounts.map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        :error="form.errors[`lines.${index}.account_id`]"
                        :maxRows="3"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                     <button type="button" @click="removeLine(index)" class="text-red-500 hover:text-red-700" :disabled="form.lines.length <= 2">
                        <TrashIcon class="w-5 h-5" />
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
         <div class="flex mt-2 mb-4">
            <button type="button" @click="addLine" class="flex items-center text-main-500 hover:text-main-700">
               <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Entri
            </button>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.configuration ? 'Ubah' : 'Tambah' }} Konfigurasi
         </AppPrimaryButton>
         <AppUtilityButton v-if="!props.configuration" type="button" @click="submitForm(true)" class="mr-2">
            Tambah & Buat Lagi
         </AppUtilityButton>
         <AppSecondaryButton @click="$inertia.visit(route('gl-event-configurations.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
