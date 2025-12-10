<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
   component: Object,
   jurisdictions: Array,
   kinds: Object,
   cascadeModes: Object,
   deductibleModes: Object,
   filters: Object,
});

const form = useForm({
   tax_jurisdiction_id: props.component?.tax_jurisdiction_id || null,
   code: props.component?.code || '',
   name: props.component?.name || '',
   kind: props.component?.kind || 'vat',
   cascade_mode: props.component?.cascade_mode || 'parallel',
   deductible_mode: props.component?.deductible_mode || 'deductible',
});

const submitted = ref(false);

const jurisdictionOptions = props.jurisdictions.map(j => ({ value: j.id, label: j.name }));
const kindOptions = Object.entries(props.kinds).map(([value, label]) => ({ value, label }));
const cascadeOptions = Object.entries(props.cascadeModes).map(([value, label]) => ({ value, label }));
const deductibleOptions = Object.entries(props.deductibleModes).map(([value, label]) => ({ value, label }));

function submitForm() {
   submitted.value = true;
   if (props.component) {
      form.put(route('tax-components.update', props.component.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('tax-components.store'), {
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
            <AppSelect
               v-model="form.tax_jurisdiction_id"
               :options="jurisdictionOptions"
               label="Yurisdiksi:"
               :error="form.errors.tax_jurisdiction_id"
               required
               placeholder="Pilih yurisdiksi"
            />

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.code"
                  label="Kode:"
                  :error="form.errors.code"
                  required
                  placeholder="Contoh: PPN, PPH21"
               />
               
               <AppInput
                  v-model="form.name"
                  label="Nama:"
                  :error="form.errors.name"
                  required
               />
            </div>
            
            <AppSelect
               v-model="form.kind"
               :options="kindOptions"
               label="Jenis:"
               :error="form.errors.kind"
               required
            />
            
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.cascade_mode"
                  :options="cascadeOptions"
                  label="Mode Kaskade:"
                  :error="form.errors.cascade_mode"
                  required
               />
               
               <AppSelect
                  v-model="form.deductible_mode"
                  :options="deductibleOptions"
                  label="Mode Pengkreditan:"
                  :error="form.errors.deductible_mode"
                  required
               />
            </div>
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi</h3>
            <p class="mb-2">Komponen pajak adalah jenis pajak spesifik dalam suatu yurisdiksi.</p>
            <ul class="list-disc list-inside">
               <li><b>Mode Kaskade:</b> Paralel = dihitung secara independen, Kumulatif = dihitung di atas pajak sebelumnya</li>
               <li><b>Mode Pengkreditan:</b> Menentukan apakah pajak dapat dikreditkan sebagai pajak masukan</li>
            </ul>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.component ? 'Ubah' : 'Tambah' }} Komponen
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('tax-components.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
