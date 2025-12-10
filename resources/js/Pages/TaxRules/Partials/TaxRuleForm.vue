<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, watch, computed } from 'vue';

const props = defineProps({
   rule: Object,
   categories: Array,
   jurisdictions: Array,
   components: Array,
   uoms: Array,
   rateTypes: Object,
   filters: Object,
});

const form = useForm({
   tax_category_id: props.rule?.tax_category_id || null,
   tax_jurisdiction_id: props.rule?.tax_jurisdiction_id || null,
   tax_component_id: props.rule?.tax_component_id || null,
   rate_type: props.rule?.rate_type || 'percent',
   rate_value: props.rule?.rate_value || 0,
   per_unit_uom_id: props.rule?.per_unit_uom_id || null,
   tax_inclusive: props.rule?.tax_inclusive || false,
   b2b_applicable: props.rule?.b2b_applicable ?? null,
   reverse_charge: props.rule?.reverse_charge || false,
   export_zero_rate: props.rule?.export_zero_rate || false,
   threshold_amount: props.rule?.threshold_amount || null,
   priority: props.rule?.priority || 10,
   effective_from: props.rule?.effective_from || new Date().toISOString().split('T')[0],
   effective_to: props.rule?.effective_to || null,
});

const submitted = ref(false);
const availableComponents = ref(props.components);

const categoryOptions = props.categories.map(c => ({ value: c.id, label: c.name }));
const jurisdictionOptions = props.jurisdictions.map(j => ({ value: j.id, label: j.name }));
const componentOptions = computed(() => availableComponents.value.map(c => ({ value: c.id, label: c.name })));
const uomOptions = props.uoms.map(u => ({ value: u.id, label: u.name }));
const rateTypeOptions = Object.entries(props.rateTypes).map(([value, label]) => ({ value, label }));

// Watch jurisdiction change to filter components
watch(() => form.tax_jurisdiction_id, (newJurisdictionId) => {
   if (newJurisdictionId) {
      availableComponents.value = props.components.filter(c => c.tax_jurisdiction_id === newJurisdictionId);
      // Reset component if not in filtered list
      if (!availableComponents.value.find(c => c.id === form.tax_component_id)) {
         form.tax_component_id = null;
      }
   } else {
      availableComponents.value = props.components;
   }
});

function submitForm() {
   submitted.value = true;
   if (props.rule) {
      form.put(route('tax-rules.update', props.rule.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('tax-rules.store'), {
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
                  v-model="form.tax_category_id"
                  :options="categoryOptions"
                  label="Kategori Pajak:"
                  :error="form.errors.tax_category_id"
                  required
                  placeholder="Pilih kategori"
               />
               
               <AppSelect
                  v-model="form.tax_jurisdiction_id"
                  :options="jurisdictionOptions"
                  label="Yurisdiksi:"
                  :error="form.errors.tax_jurisdiction_id"
                  required
                  placeholder="Pilih yurisdiksi"
               />
            </div>

            <AppSelect
               v-model="form.tax_component_id"
               :options="componentOptions"
               label="Komponen Pajak:"
               :error="form.errors.tax_component_id"
               required
               placeholder="Pilih komponen (pilih yurisdiksi dulu)"
               :disabled="!form.tax_jurisdiction_id"
            />
            
            <div class="grid grid-cols-3 gap-4">
               <AppSelect
                  v-model="form.rate_type"
                  :options="rateTypeOptions"
                  label="Tipe Tarif:"
                  :error="form.errors.rate_type"
                  required
               />
               
               <AppInput
                  v-model="form.rate_value"
                  label="Tarif:"
                  :error="form.errors.rate_value"
                  type="number"
                  step="0.000001"
                  min="0"
                  required
               />

               <AppSelect
                  v-if="form.rate_type === 'fixed_per_unit'"
                  v-model="form.per_unit_uom_id"
                  :options="uomOptions"
                  label="UOM Per Unit:"
                  :error="form.errors.per_unit_uom_id"
                  placeholder="Pilih UOM"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.effective_from"
                  label="Berlaku Dari:"
                  :error="form.errors.effective_from"
                  type="date"
                  required
               />
               
               <AppInput
                  v-model="form.effective_to"
                  label="Berlaku Sampai:"
                  :error="form.errors.effective_to"
                  type="date"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.priority"
                  label="Prioritas:"
                  :error="form.errors.priority"
                  type="number"
                  min="0"
               />
               
               <AppInput
                  v-model="form.threshold_amount"
                  label="Ambang Batas:"
                  :error="form.errors.threshold_amount"
                  type="number"
                  step="0.01"
                  min="0"
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4 mt-4">
               <div class="space-y-2">
                  <AppCheckbox
                     v-model:checked="form.tax_inclusive"
                     label="Harga Termasuk Pajak"
                  />
                  <AppCheckbox
                     v-model:checked="form.reverse_charge"
                     label="Reverse Charge"
                  />
               </div>
               <div class="space-y-2">
                  <AppCheckbox
                     v-model:checked="form.export_zero_rate"
                     label="Ekspor Tarif Nol"
                  />
               </div>
            </div>
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi</h3>
            <p class="mb-2">Aturan pajak menentukan tarif yang diterapkan berdasarkan kombinasi kategori, yurisdiksi, dan komponen.</p>
            <ul class="list-disc list-inside">
               <li><b>Prioritas:</b> Aturan dengan prioritas lebih rendah diproses lebih dulu</li>
               <li><b>Termasuk Pajak:</b> Harga sudah termasuk pajak</li>
               <li><b>Reverse Charge:</b> Pajak dipungut oleh pembeli</li>
               <li><b>Ekspor Tarif Nol:</b> Tarif 0% untuk ekspor</li>
            </ul>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.rule ? 'Ubah' : 'Tambah' }} Aturan
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('tax-rules.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
