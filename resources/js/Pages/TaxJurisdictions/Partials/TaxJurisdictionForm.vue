<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
   jurisdiction: Object,
   parentJurisdictions: Array,
   levels: Object,
   filters: Object,
});

const form = useForm({
   parent_id: props.jurisdiction?.parent_id || null,
   code: props.jurisdiction?.code || '',
   name: props.jurisdiction?.name || '',
   country_code: props.jurisdiction?.country_code || 'ID',
   level: props.jurisdiction?.level || 'country',
   tax_authority: props.jurisdiction?.tax_authority || '',
});

const submitted = ref(false);

const levelOptions = Object.entries(props.levels).map(([value, label]) => ({ value, label }));

const parentOptions = props.parentJurisdictions.map(j => ({ value: j.id, label: j.name }));

function submitForm() {
   submitted.value = true;
   if (props.jurisdiction) {
      form.put(route('tax-jurisdictions.update', props.jurisdiction.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('tax-jurisdictions.store'), {
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
               <AppInput
                  v-model="form.code"
                  label="Kode:"
                  :error="form.errors.code"
                  placeholder="Contoh: ID, ID-JK"
               />
               
               <AppInput
                  v-model="form.name"
                  label="Nama:"
                  :error="form.errors.name"
                  required
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.country_code"
                  label="Kode Negara (ISO 2):"
                  :error="form.errors.country_code"
                  maxlength="2"
                  required
               />
               
               <AppSelect
                  v-model="form.level"
                  :options="levelOptions"
                  label="Level:"
                  :error="form.errors.level"
                  required
               />
            </div>
            
            <AppInput
               v-model="form.tax_authority"
               label="Otoritas Pajak:"
               :error="form.errors.tax_authority"
               placeholder="Contoh: Direktorat Jenderal Pajak"
            />

            <AppSelect
               v-model="form.parent_id"
               :options="parentOptions"
               label="Yurisdiksi Induk:"
               :error="form.errors.parent_id"
               placeholder="Pilih yurisdiksi induk (opsional)"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi</h3>
            <p class="mb-2">Yurisdiksi pajak menentukan wilayah penerapan pajak.</p>
            <ul class="list-disc list-inside">
               <li>Kode negara menggunakan format ISO 3166-1 alpha-2</li>
               <li>Level menentukan tingkat yurisdiksi (negara, provinsi, dll.)</li>
               <li>Yurisdiksi dapat memiliki struktur hierarki</li>
            </ul>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.jurisdiction ? 'Ubah' : 'Tambah' }} Yurisdiksi
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('tax-jurisdictions.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
