<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, watch, onMounted } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';

const props = defineProps({
   bom: Object,
   companies: Array,
   branches: Array,
   finishedProducts: Array,
   componentProducts: Array,
   filters: Object,
});

const form = useForm({
   company_id: props.bom?.company_id || null,
   branch_id: props.bom?.branch_id || null,
   finished_product_id: props.bom?.finished_product_id || null,
   finished_product_variant_id: props.bom?.finished_product_variant_id || null,
   finished_quantity: props.bom?.finished_quantity || 1,
   finished_uom_id: props.bom?.finished_uom_id || null,
   name: props.bom?.name || '',
   description: props.bom?.description || '',
   version: props.bom?.version || '1.0',
   effective_date: props.bom?.effective_date || '',
   expiration_date: props.bom?.expiration_date || '',
   status: props.bom?.status || 'draft',
   is_default: props.bom?.is_default || false,
   lines: props.bom?.bom_lines || [
      { component_product_id: null, component_product_variant_id: null, quantity_per: 1, uom_id: null, scrap_percentage: 0, backflush: false, operation: '', notes: '' },
   ],
});

const submitted = ref(false);
const selectedCompany = ref(props.bom?.company_id || (props.companies.length > 1 ? null : props.companies[0].id));

watch(selectedCompany, (newCompanyId) => {
   form.company_id = newCompanyId;
   if (!props.bom) {
      router.reload({ only: ['branches', 'finishedProducts', 'componentProducts'], data: { company_id: newCompanyId } });
   }
}, { immediate: true });

watch(
   () => props.branches, 
   (newBranches) => {
      if (!props.bom && newBranches.length === 1) {
         form.branch_id = newBranches[0].id;
      }
   }, 
   { immediate: true }
);

const finishedProductVariants = ref([]);

watch(
   () => form.finished_product_id, 
   (newProductId) => {
      if (newProductId) {
         const product = props.finishedProducts.find(p => p.id === newProductId);
         if (product && product.variants) {
            finishedProductVariants.value = product.variants;
         }
      }
   }, 
   { immediate: true }
);

const componentProductVariants = ref([]);

watch(
   () => form.lines,
   (newLines) => {
      if (newLines.length > 0) {
         const product = props.componentProducts.find(p => p.id === newLines[0].component_product_id);
         if (product && product.variants) {
            componentProductVariants.value = product.variants;
         }
      }
   },
   { immediate: true, deep: true }
);

onMounted(() => {
   selectedCompany.value = props.bom?.company_id || (props.companies.length > 1 ? null : props.companies[0].id);
   if (!props.bom && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
});

function addLine() {
   form.lines.push({ component_product_id: null, component_product_variant_id: null, quantity_per: 1, uom_id: null, scrap_percentage: 0, backflush: false, operation: '', notes: '' });
}

function removeLine(index) {
   form.lines.splice(index, 1);
}

function updateSelectedUom(index) {
   const selectedProduct = props.componentProducts.find(p => p.id === form.lines[index].component_product_id);
   if (selectedProduct) {
      form.lines[index].uom_id = selectedProduct.default_uom_id;
   }
}

function submitForm() {
   submitted.value = true;
   if (props.bom) {
      form.put(route('bill-of-materials.update', props.bom.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('bill-of-materials.store'), {
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

function getProductUoms(productId) {
    const product = props.componentProducts.find(p => p.id === productId);
    return product ? [product.default_uom] : [];
}

function getFinishedProductUoms(productId) {
    const product = props.finishedProducts.find(p => p.id === productId);
    return product ? [product.default_uom] : [];
}
</script>

<template>
   <form @submit.prevent="submitForm()" class="space-y-4">
      <div class="flex justify-between">
         <div class="w-2/3 max-w-2xl mr-8">
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="selectedCompany"
                  :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                  label="Perusahaan:"
                  placeholder="Pilih Perusahaan"
                  :error="form.errors.company_id"
                  :disabled="!!props.bom"
                  required
               />

               <AppSelect
                  v-model="form.branch_id"
                  :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang:"
                  placeholder="Pilih Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.bom"
                  required
               />
            </div>

            <div class="grid grid-cols-3 gap-4">
               <AppSelect
                  v-model="form.finished_product_id"
                  :options="props.finishedProducts.map(product => ({ value: product.id, label: `${product.name}` }))"
                  label="Produk Jadi:"
                  placeholder="Pilih Produk Jadi"
                  :error="form.errors.finished_product_id"
                  required
               />

               <AppSelect
                  v-model="form.finished_product_variant_id"
                  :options="finishedProductVariants.map(variant => ({ value: variant.id, label: `${variant.sku} - ${variant.name}` }))"
                  label="Varian Produk:"
                  placeholder="Pilih Varian (Opsional)"
                  :error="form.errors.finished_product_variant_id"
               />

               <AppInput
                  v-model="form.finished_quantity"
                  type="number"
                  step="0.001"
                  label="Kuantitas Jadi:"
                  :error="form.errors.finished_quantity"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.finished_uom_id"
                  :options="getFinishedProductUoms(form.finished_product_id).map(uom => ({ value: uom.id, label: uom.name }))"
                  label="Satuan Jadi:"
                  placeholder="Pilih Satuan"
                  :error="form.errors.finished_uom_id"
                  required
               />

               <AppInput
                  v-model="form.version"
                  label="Versi:"
                  :error="form.errors.version"
                  placeholder="1.0"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.effective_date"
                  type="date"
                  label="Tanggal Efektif:"
                  :error="form.errors.effective_date"
               />

               <AppInput
                  v-model="form.expiration_date"
                  type="date"
                  label="Tanggal Kadaluarsa:"
                  :error="form.errors.expiration_date"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.status"
                  :options="[
                     { value: 'draft', label: 'Draft' },
                     { value: 'active', label: 'Active' },
                     { value: 'inactive', label: 'Inactive' }
                  ]"
                  label="Status:"
                  :error="form.errors.status"
                  required
               />

               <div class="flex items-center mt-6">
                  <AppCheckbox
                     v-model="form.is_default"
                     label="Jadikan Default"
                     :error="form.errors.is_default"
                  />
               </div>
            </div>

            <AppInput
               v-model="form.name"
               label="Nama BOM:"
               :error="form.errors.name"
               required
            />

            <AppTextarea
               v-model="form.description"
               label="Deskripsi:"
               :error="form.errors.description"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi BOM</h3>
            <p class="mb-2">Bill of Materials (BOM) mendefinisikan komponen yang diperlukan untuk memproduksi satu unit produk jadi.</p>
            <ul class="list-disc list-inside">
               <li>Pilih produk jadi dan tentukan kuantitas yang akan diproduksi</li>
               <li>Tambahkan komponen yang diperlukan beserta kuantitas per unit</li>
               <li>Scrap percentage menentukan jumlah material tambahan untuk kerugian produksi</li>
               <li>Backflush otomatis mengeluarkan komponen saat produksi selesai</li>
               <li>Operation menentukan langkah produksi untuk komponen tersebut</li>
            </ul>
         </div>
      </div>

      <div class="overflow-x-auto">
         <table class="min-w-full bg-white border border-gray-300">
            <thead>
               <tr class="bg-gray-100">
                  <th class="border border-gray-300 text-sm min-w-48 lg:min-w-72 px-1.5 py-1.5">Komponen</th>
                  <th class="border border-gray-300 text-sm min-w-48 lg:min-w-72 px-1.5 py-1.5">Varian (Opsional)</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Kuantitas per Unit</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Satuan</th>
                  <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Scrap (%)</th>
                  <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Backflush</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Operasi</th>
                  <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Catatan</th>
                  <th class="border border-gray-300 px-1.5 py-1.5"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="(line, index) in form.lines" :key="index">
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.component_product_id"
                        :options="props.componentProducts.map(product => ({ value: product.id, label: product.name }))"
                        :error="form.errors[`lines.${index}.component_product_id`]"
                        :maxRows="3"
                        @update:modelValue="updateSelectedUom(index)"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.component_product_variant_id"
                        :options="componentProductVariants.map(variant => ({ value: variant.id, label: `${variant.sku} - ${variant.name}` }))"
                        :error="form.errors[`lines.${index}.component_product_variant_id`]"
                        :maxRows="3"
                        placeholder="Pilih Varian (Opsional)"
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="line.quantity_per"
                        type="number"
                        step="0.001"
                        :error="form.errors[`lines.${index}.quantity_per`]"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppSelect
                        v-model="line.uom_id"
                        :options="getProductUoms(line.component_product_id).map(uom => ({ value: uom.id, label: uom.name }))"
                        :error="form.errors[`lines.${index}.uom_id`]"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="line.scrap_percentage"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        :error="form.errors[`lines.${index}.scrap_percentage`]"
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-center">
                     <AppCheckbox
                        v-model="line.backflush"
                        :error="form.errors[`lines.${index}.backflush`]"
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="line.operation"
                        :error="form.errors[`lines.${index}.operation`]"
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="line.notes"
                        :error="form.errors[`lines.${index}.notes`]"
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                     <button type="button" @click="removeLine(index)" class="text-red-500 hover:text-red-700 mb-4">
                        <TrashIcon class="w-5 h-5" />
                     </button>
                  </td>
               </tr>
            </tbody>
         </table>
         <div class="flex mt-2 mb-4">
            <button type="button" @click="addLine" class="flex items-center text-main-500 hover:text-main-700">
               <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Komponen
            </button>
         </div>
      </div>

      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit">
            {{ props.bom ? 'Ubah' : 'Tambah' }} BOM
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('bill-of-materials.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
