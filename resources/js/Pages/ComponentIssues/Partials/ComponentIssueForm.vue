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
import axios from 'axios';

const props = defineProps({
   componentIssue: Object,
   companies: Array,
   branches: Array,
   locations: Array,
   workOrders: Array,
   filters: Object,
});

const form = useForm({
   work_order_id: props.componentIssue?.work_order_id || null,
   branch_id: props.componentIssue?.branch_id || null,
   location_from_id: props.componentIssue?.location_from_id || null,
   issue_date: props.componentIssue?.issue_date || new Date().toISOString().split('T')[0],
   notes: props.componentIssue?.notes || '',
   lines: props.componentIssue?.component_issue_lines || [],
   create_another: false,
});

const submitted = ref(false);
const selectedCompany = ref(props.componentIssue?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));
const selectedWorkOrder = ref(null);
const lotsByVariant = ref({});
const serialsByVariant = ref({});

const minDate = ref(props.componentIssue ? new Date(new Date(props.componentIssue.issue_date).getFullYear(), 0, 1).toLocaleDateString('en-CA') : null);
const maxDate = ref(props.componentIssue ? new Date(new Date(props.componentIssue.issue_date).getFullYear(), 11, 31).toLocaleDateString('en-CA') : null);

watch(selectedCompany, (newCompanyId) => {
   if (!props.componentIssue && newCompanyId) {
      router.reload({ only: ['branches', 'workOrders', 'locations'], data: { company_id: newCompanyId } });
   }
}, { immediate: true });

watch(() => form.branch_id, (newBranchId) => {
   if (!props.componentIssue && newBranchId) {
      router.reload({ only: ['locations'], data: { branch_id: newBranchId } });
   }
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.componentIssue && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

watch(() => form.work_order_id, (newWorkOrderId) => {
   if (newWorkOrderId && !props.componentIssue) {
      const workOrder = props.workOrders.find(wo => wo.id === newWorkOrderId);
      if (workOrder && workOrder.bom?.bom_lines) {
         // Pre-populate lines from BOM
         form.lines = workOrder.bom.bom_lines.map((bomLine, index) => {
            const variantId = bomLine.component_product_variant_id;
            if (variantId) {
               loadLotsForVariant(variantId);
               loadSerialsForVariant(variantId);
            }
            return {
               bom_line_id: bomLine.id,
               component_product_id: bomLine.component_product_id,
               component_product_variant_id: variantId,
               quantity_issued: bomLine.quantity_per || 0,
               uom_id: bomLine.uom_id,
               lot_id: null,
               serial_id: null,
               backflush: bomLine.backflush || false,
               notes: bomLine.notes || null,
            };
         });
      }
   }
});

async function loadLotsForVariant(variantId) {
   if (!variantId || lotsByVariant.value[variantId]) {
      return;
   }
   try {
      const response = await axios.get(route('api.lots.by-product-variant', variantId));
      lotsByVariant.value[variantId] = response.data;
   } catch (error) {
      console.error('Error loading lots:', error);
      lotsByVariant.value[variantId] = [];
   }
}

async function loadSerialsForVariant(variantId) {
   if (!variantId || serialsByVariant.value[variantId]) {
      return;
   }
   try {
      const response = await axios.get(route('api.serials.by-product-variant', variantId));
      serialsByVariant.value[variantId] = response.data;
   } catch (error) {
      console.error('Error loading serials:', error);
      serialsByVariant.value[variantId] = [];
   }
}

watch(() => form.lines, (newLines) => {
   newLines.forEach((line) => {
      if (line.component_product_variant_id) {
         loadLotsForVariant(line.component_product_variant_id);
         loadSerialsForVariant(line.component_product_variant_id);
      }
   });
}, { deep: true, immediate: true });

onMounted(() => {
   selectedCompany.value = props.componentIssue?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
   if (!props.componentIssue && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
   if (props.componentIssue) {
      selectedWorkOrder.value = props.componentIssue.work_order_id;
      // Load lots and serials for existing lines
      props.componentIssue.component_issue_lines?.forEach((line) => {
         if (line.component_product_variant_id) {
            loadLotsForVariant(line.component_product_variant_id);
            loadSerialsForVariant(line.component_product_variant_id);
         }
      });
   }
});

function addLine() {
   form.lines.push({
      bom_line_id: null,
      component_product_id: null,
      component_product_variant_id: null,
      quantity_issued: 0,
      uom_id: null,
      lot_id: null,
      serial_id: null,
      backflush: false,
      notes: null,
   });
}

function removeLine(index) {
   form.lines.splice(index, 1);
}

function onVariantChange(index) {
   const line = form.lines[index];
   if (line.component_product_variant_id) {
      loadLotsForVariant(line.component_product_variant_id);
      loadSerialsForVariant(line.component_product_variant_id);
   }
   // Clear lot and serial when variant changes
   line.lot_id = null;
   line.serial_id = null;
}

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;

   if (props.componentIssue) {
      form.put(route('component-issues.update', props.componentIssue.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('component-issues.store'), {
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
                  :disabled="!!props.componentIssue"
                  required
               />
               
               <AppSelect
                  v-model="form.branch_id"
                  :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang:"
                  placeholder="Pilih Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.componentIssue"
                  required
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.work_order_id"
                  :options="props.workOrders.map(wo => ({ value: wo.id, label: wo.wo_number + ' - ' + (wo.bom?.finished_product?.name || '') }))"
                  label="Work Order:"
                  placeholder="Pilih Work Order"
                  :error="form.errors.work_order_id"
                  :disabled="!!props.componentIssue"
                  required
               />
               
               <AppInput
                  v-model="form.issue_date"
                  type="date"
                  label="Tanggal Issue:"
                  :error="form.errors.issue_date"
                  :min="minDate"
                  :max="maxDate"
                  required
               />
            </div>

            <AppSelect
               v-model="form.location_from_id"
               :options="props.locations.map(loc => ({ value: loc.id, label: loc.code + ' - ' + loc.name }))"
               label="Location From:"
               placeholder="Pilih Location (Optional)"
               :error="form.errors.location_from_id"
            />

            <AppTextarea
               v-model="form.notes"
               label="Catatan:"
               :error="form.errors.notes"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Component Issue</h3>
            <p class="mb-2">Component Issue digunakan untuk mencatat konsumsi komponen dari Work Order.</p>
            <ul class="list-disc list-inside">
               <li>Pilih Work Order yang sesuai</li>
               <li>Pilih tanggal issue</li>
               <li>Pilih location dari mana komponen di-issue (opsional)</li>
               <li>Tambahkan komponen yang di-issue dengan quantity yang sesuai</li>
               <li>Pilih lot dan serial number jika diperlukan</li>
               <li>Catatan opsional untuk informasi tambahan</li>
            </ul>
         </div>
      </div>
      
      <div class="overflow-x-auto">
         <table class="min-w-full bg-white border border-gray-300">
            <thead>
               <tr class="bg-gray-100">
                  <th class="border border-gray-300 text-sm px-1.5 py-1.5">No.</th>
                  <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Component</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Quantity</th>
                  <th class="border border-gray-300 text-sm px-1.5 py-1.5">UOM</th>
                  <th class="border border-gray-300 text-sm px-1.5 py-1.5">Lot</th>
                  <th class="border border-gray-300 text-sm px-1.5 py-1.5">Serial</th>
                  <th class="border border-gray-300 px-1.5 py-1.5"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="(line, index) in form.lines" :key="index">
                  <td class="border border-gray-300 px-1.5 py-1.5">{{ index + 1 }}</td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <div class="text-sm">
                        <div v-if="line.component_product_id">
                           {{ props.workOrders.find(wo => wo.id === form.work_order_id)?.bom?.bom_lines?.find(bl => bl.component_product_id === line.component_product_id)?.component_product?.name || 'N/A' }}
                        </div>
                        <div v-else class="text-gray-400">Pilih Work Order</div>
                     </div>
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="line.quantity_issued"
                        type="number"
                        step="0.000001"
                        :error="form.errors[`lines.${index}.quantity_issued`]"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <div class="text-sm">
                        {{ props.workOrders.find(wo => wo.id === form.work_order_id)?.bom?.bom_lines?.find(bl => bl.component_product_id === line.component_product_id)?.uom?.name || 'N/A' }}
                     </div>
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.lot_id"
                        :options="(lotsByVariant[line.component_product_variant_id] || []).map(lot => ({ value: lot.id, label: lot.lot_code }))"
                        placeholder="Pilih Lot"
                        :error="form.errors[`lines.${index}.lot_id`]"
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                        @update:modelValue="() => {}"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.serial_id"
                        :options="(serialsByVariant[line.component_product_variant_id] || []).map(serial => ({ value: serial.id, label: serial.serial_no }))"
                        placeholder="Pilih Serial"
                        :error="form.errors[`lines.${index}.serial_id`]"
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                        @update:modelValue="() => {}"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                     <button type="button" @click="removeLine(index)" class="text-red-500 hover:text-red-700">
                        <TrashIcon class="w-5 h-5" />
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
         <div class="flex mt-2 mb-4">
            <button type="button" @click="addLine" class="flex items-center text-main-500 hover:text-main-700">
               <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Line
            </button>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.componentIssue ? 'Ubah' : 'Tambah' }} Component Issue
         </AppPrimaryButton>
         <AppUtilityButton v-if="!props.componentIssue" type="button" @click="submitForm(true)" class="mr-2">
            Tambah & Buat Lagi
         </AppUtilityButton>
         <AppSecondaryButton @click="$inertia.visit(route('component-issues.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
