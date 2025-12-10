<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref } from 'vue';

const props = defineProps({
   category: Object,
   companies: Array,
   appliesTo: Object,
   behaviors: Object,
   filters: Object,
});

const form = useForm({
   company_id: props.category?.company_id || (props.companies.length === 1 ? props.companies[0].id : null),
   code: props.category?.code || '',
   name: props.category?.name || '',
   description: props.category?.description || '',
   applies_to: props.category?.applies_to || 'both',
   default_behavior: props.category?.default_behavior || 'taxable',
});

const submitted = ref(false);

const companyOptions = props.companies.map(c => ({ value: c.id, label: c.name }));
const appliesToOptions = Object.entries(props.appliesTo).map(([value, label]) => ({ value, label }));
const behaviorOptions = Object.entries(props.behaviors).map(([value, label]) => ({ value, label }));

function submitForm() {
   submitted.value = true;
   if (props.category) {
      form.put(route('tax-categories.update', props.category.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('tax-categories.store'), {
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
               v-model="form.company_id"
               :options="companyOptions"
               label="Perusahaan:"
               :error="form.errors.company_id"
               required
               placeholder="Pilih perusahaan"
            />

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.code"
                  label="Kode:"
                  :error="form.errors.code"
                  required
                  placeholder="Contoh: STANDARD_GOODS"
               />
               
               <AppInput
                  v-model="form.name"
                  label="Nama:"
                  :error="form.errors.name"
                  required
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.applies_to"
                  :options="appliesToOptions"
                  label="Berlaku Untuk:"
                  :error="form.errors.applies_to"
                  required
               />
               
               <AppSelect
                  v-model="form.default_behavior"
                  :options="behaviorOptions"
                  label="Perilaku Default:"
                  :error="form.errors.default_behavior"
                  required
               />
            </div>
            
            <AppTextarea
               v-model="form.description"
               label="Deskripsi:"
               :error="form.errors.description"
               rows="3"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi</h3>
            <p class="mb-2">Kategori pajak mengelompokkan barang/jasa berdasarkan perlakuan pajaknya.</p>
            <ul class="list-disc list-inside">
               <li><b>Kena Pajak:</b> Dikenakan pajak dengan tarif normal</li>
               <li><b>Tarif Nol:</b> Dikenakan pajak 0% (ekspor)</li>
               <li><b>Bebas Pajak:</b> Dikecualikan dari pengenaan pajak</li>
               <li><b>Di Luar Lingkup:</b> Tidak termasuk objek pajak</li>
            </ul>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.category ? 'Ubah' : 'Tambah' }} Kategori
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('tax-categories.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
