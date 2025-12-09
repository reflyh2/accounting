<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';
import AppTextarea from '@/Components/AppTextarea.vue';
import axios from 'axios';

const props = defineProps({
   finishedGoodsReceipt: Object,
   companies: Array,
   branches: Array,
   locations: Array,
   workOrders: Array,
   filters: Object,
});

const form = useForm({
   work_order_id: props.finishedGoodsReceipt?.work_order_id || null,
   branch_id: props.finishedGoodsReceipt?.branch_id || null,
   finished_product_variant_id: props.finishedGoodsReceipt?.finished_product_variant_id || null,
   location_to_id: props.finishedGoodsReceipt?.location_to_id || null,
   uom_id: props.finishedGoodsReceipt?.uom_id || null,
   receipt_date: props.finishedGoodsReceipt?.receipt_date || new Date().toISOString().split('T')[0],
   quantity_good: props.finishedGoodsReceipt?.quantity_good || 0,
   quantity_scrap: props.finishedGoodsReceipt?.quantity_scrap || 0,
   labor_cost: props.finishedGoodsReceipt?.labor_cost || 0,
   overhead_cost: props.finishedGoodsReceipt?.overhead_cost || 0,
   lot_id: props.finishedGoodsReceipt?.lot_id || null,
   serial_id: props.finishedGoodsReceipt?.serial_id || null,
   notes: props.finishedGoodsReceipt?.notes || '',
   create_another: false,
});

const submitted = ref(false);
const selectedCompany = ref(props.finishedGoodsReceipt?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));
const lotsByVariant = ref({});
const serialsByVariant = ref({});
const materialCost = ref(0);

const minDate = ref(props.finishedGoodsReceipt ? new Date(new Date(props.finishedGoodsReceipt.receipt_date).getFullYear(), 0, 1).toLocaleDateString('en-CA') : null);
const maxDate = ref(props.finishedGoodsReceipt ? new Date(new Date(props.finishedGoodsReceipt.receipt_date).getFullYear(), 11, 31).toLocaleDateString('en-CA') : null);

watch(selectedCompany, (newCompanyId) => {
   if (!props.finishedGoodsReceipt && newCompanyId) {
      router.reload({ only: ['branches', 'workOrders', 'locations'], data: { company_id: newCompanyId } });
   }
}, { immediate: true });

watch(() => form.branch_id, (newBranchId) => {
   if (!props.finishedGoodsReceipt && newBranchId) {
      router.reload({ only: ['locations'], data: { branch_id: newBranchId } });
   }
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.finishedGoodsReceipt && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

watch(() => form.work_order_id, async (newWorkOrderId) => {
   if (newWorkOrderId) {
      const workOrder = props.workOrders.find(wo => wo.id === newWorkOrderId);
      if (workOrder && !props.finishedGoodsReceipt) {
         // Auto-populate finished product variant from BOM
         if (workOrder.bom?.finished_product_variant_id) {
            form.finished_product_variant_id = workOrder.bom.finished_product_variant_id;
            if (workOrder.bom.finished_product_variant_id) {
               loadLotsForVariant(workOrder.bom.finished_product_variant_id);
               loadSerialsForVariant(workOrder.bom.finished_product_variant_id);
            }
         }
         // Auto-populate UOM from BOM
         if (workOrder.bom?.finished_uom_id) {
            form.uom_id = workOrder.bom.finished_uom_id;
         }
         // Calculate material cost from component issues
         calculateMaterialCost(workOrder);
      } else if (workOrder && props.finishedGoodsReceipt) {
         calculateMaterialCost(workOrder);
      }
   }
});

async function calculateMaterialCost(workOrder) {
   if (workOrder.component_issues) {
      const total = workOrder.component_issues
         .filter(ci => ci.status === 'posted')
         .reduce((sum, ci) => sum + (parseFloat(ci.total_material_cost) || 0), 0);
      materialCost.value = total;
   } else {
      materialCost.value = 0;
   }
}

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

watch(() => form.finished_product_variant_id, (newVariantId) => {
   if (newVariantId) {
      loadLotsForVariant(newVariantId);
      loadSerialsForVariant(newVariantId);
      // Clear lot and serial when variant changes
      form.lot_id = null;
      form.serial_id = null;
   }
});

const totalCost = computed(() => {
   const material = materialCost.value || 0;
   const labor = parseFloat(form.labor_cost) || 0;
   const overhead = parseFloat(form.overhead_cost) || 0;
   return material + labor + overhead;
});

const unitCost = computed(() => {
   const qty = parseFloat(form.quantity_good) || 0;
   if (qty <= 0) {
      return 0;
   }
   return totalCost.value / qty;
});

onMounted(() => {
   selectedCompany.value = props.finishedGoodsReceipt?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
   if (!props.finishedGoodsReceipt && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
   if (props.finishedGoodsReceipt) {
      // Use stored material cost if receipt is posted, otherwise calculate from component issues
      if (props.finishedGoodsReceipt.status === 'posted' && props.finishedGoodsReceipt.total_material_cost) {
         materialCost.value = parseFloat(props.finishedGoodsReceipt.total_material_cost) || 0;
      } else if (props.finishedGoodsReceipt.work_order) {
         calculateMaterialCost(props.finishedGoodsReceipt.work_order);
      }
      // Load lots and serials for existing variant
      if (props.finishedGoodsReceipt.finished_product_variant_id) {
         loadLotsForVariant(props.finishedGoodsReceipt.finished_product_variant_id);
         loadSerialsForVariant(props.finishedGoodsReceipt.finished_product_variant_id);
      }
   }
});

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;

   if (props.finishedGoodsReceipt) {
      form.put(route('finished-goods-receipts.update', props.finishedGoodsReceipt.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('finished-goods-receipts.store'), {
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

const selectedWorkOrder = computed(() => {
   if (!form.work_order_id) {
      return null;
   }
   return props.workOrders.find(wo => wo.id === form.work_order_id);
});

const finishedProductVariantOptions = computed(() => {
   if (!selectedWorkOrder.value || !selectedWorkOrder.value.bom?.finished_product_variant) {
      return [];
   }
   return [{
      value: selectedWorkOrder.value.bom.finished_product_variant.id,
      label: selectedWorkOrder.value.bom.finished_product_variant.name || selectedWorkOrder.value.bom.finished_product?.name
   }];
});
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
                  :disabled="!!props.finishedGoodsReceipt"
                  required
               />
               
               <AppSelect
                  v-model="form.branch_id"
                  :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang:"
                  placeholder="Pilih Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.finishedGoodsReceipt"
                  required
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.work_order_id"
                  :options="props.workOrders.map(wo => ({ value: wo.id, label: wo.wo_number, description: wo.bom?.finished_product?.name || '' }))"
                  label="Work Order:"
                  placeholder="Pilih Work Order"
                  :error="form.errors.work_order_id"
                  :disabled="!!props.finishedGoodsReceipt"
                  required
               />
               
               <AppInput
                  v-model="form.receipt_date"
                  type="date"
                  label="Tanggal Receipt:"
                  :error="form.errors.receipt_date"
                  :min="minDate"
                  :max="maxDate"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.finished_product_variant_id"
                  :options="finishedProductVariantOptions"
                  label="Finished Product Variant:"
                  placeholder="Pilih Finished Product Variant"
                  :error="form.errors.finished_product_variant_id"
                  :disabled="!form.work_order_id || !!props.finishedGoodsReceipt"
                  required
               />
               
               <AppSelect
                  v-model="form.uom_id"
                  :options="selectedWorkOrder?.bom?.finished_uom ? [{ value: selectedWorkOrder.bom.finished_uom.id, label: selectedWorkOrder.bom.finished_uom.name }] : []"
                  label="UOM:"
                  placeholder="Pilih UOM"
                  :error="form.errors.uom_id"
                  :disabled="!form.work_order_id || !!props.finishedGoodsReceipt"
                  required
               />
            </div>

            <AppSelect
               v-model="form.location_to_id"
               :options="props.locations.map(loc => ({ value: loc.id, label: loc.code + ' - ' + loc.name }))"
               label="Location To:"
               placeholder="Pilih Location"
               :error="form.errors.location_to_id"
               required
            />

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.quantity_good"
                  type="number"
                  step="0.000001"
                  min="0.000001"
                  label="Quantity Good:"
                  :error="form.errors.quantity_good"
                  required
               />
               
               <AppInput
                  v-model="form.quantity_scrap"
                  type="number"
                  step="0.000001"
                  min="0"
                  label="Quantity Scrap:"
                  :error="form.errors.quantity_scrap"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.labor_cost"
                  type="number"
                  step="0.000001"
                  min="0"
                  label="Labor Cost:"
                  :error="form.errors.labor_cost"
               />
               
               <AppInput
                  v-model="form.overhead_cost"
                  type="number"
                  step="0.000001"
                  min="0"
                  label="Overhead Cost:"
                  :error="form.errors.overhead_cost"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-if="form.finished_product_variant_id"
                  v-model="form.lot_id"
                  :options="(lotsByVariant[form.finished_product_variant_id] || []).map(lot => ({ value: lot.id, label: lot.lot_code }))"
                  label="Lot:"
                  placeholder="Pilih Lot (Optional)"
                  :error="form.errors.lot_id"
               />
               
               <AppSelect
                  v-if="form.finished_product_variant_id"
                  v-model="form.serial_id"
                  :options="(serialsByVariant[form.finished_product_variant_id] || []).map(serial => ({ value: serial.id, label: serial.serial_no }))"
                  label="Serial:"
                  placeholder="Pilih Serial (Optional)"
                  :error="form.errors.serial_id"
               />
            </div>

            <AppTextarea
               v-model="form.notes"
               label="Catatan:"
               :error="form.errors.notes"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Finished Goods Receipt</h3>
            <p class="mb-2">Finished Goods Receipt digunakan untuk mencatat penerimaan finished goods dari Work Order.</p>
            <ul class="list-disc list-inside mb-4">
               <li>Pilih Work Order yang sesuai</li>
               <li>Finished Product Variant akan terisi otomatis dari BOM</li>
               <li>Pilih location tujuan penerimaan</li>
               <li>Masukkan quantity good dan scrap</li>
               <li>Masukkan labor cost dan overhead cost (opsional)</li>
               <li>Material cost dihitung dari component issues yang sudah di-post</li>
               <li>Unit cost = (Material + Labor + Overhead) / Quantity Good</li>
            </ul>
            <div v-if="form.work_order_id" class="mt-4 p-3 bg-white rounded border border-gray-300">
               <h4 class="font-semibold mb-2">Cost Summary:</h4>
               <div class="space-y-1 text-xs">
                  <div class="flex justify-between">
                     <span>Material Cost:</span>
                     <span>{{ formatNumber(materialCost) }}</span>
                  </div>
                  <div class="flex justify-between">
                     <span>Labor Cost:</span>
                     <span>{{ formatNumber(form.labor_cost || 0) }}</span>
                  </div>
                  <div class="flex justify-between">
                     <span>Overhead Cost:</span>
                     <span>{{ formatNumber(form.overhead_cost || 0) }}</span>
                  </div>
                  <div class="flex justify-between font-semibold border-t pt-1">
                     <span>Total Cost:</span>
                     <span>{{ formatNumber(totalCost) }}</span>
                  </div>
                  <div class="flex justify-between font-semibold border-t pt-1">
                     <span>Unit Cost:</span>
                     <span>{{ formatNumber(unitCost) }}</span>
                  </div>
               </div>
            </div>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.finishedGoodsReceipt ? 'Ubah' : 'Tambah' }} Finished Goods Receipt
         </AppPrimaryButton>
         <AppUtilityButton v-if="!props.finishedGoodsReceipt" type="button" @click="submitForm(true)" class="mr-2">
            Tambah & Buat Lagi
         </AppUtilityButton>
         <AppSecondaryButton @click="$inertia.visit(route('finished-goods-receipts.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>

