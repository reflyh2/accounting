<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, watch, onMounted } from 'vue';
import { formatNumber } from '@/utils/numberFormat';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
   workOrder: Object,
   companies: Array,
   branches: Array,
   boms: Array,
   locations: Array,
   filters: Object,
});

const form = useForm({
   company_id: props.workOrder?.company_id || null,
   branch_id: props.workOrder?.branch_id || null,
   bom_id: props.workOrder?.bom_id || null,
   finished_product_variant_id: props.workOrder?.finished_product_variant_id || null,
   wip_location_id: props.workOrder?.wip_location_id || null,
   quantity_planned: props.workOrder?.quantity_planned || 1,
   scheduled_start_date: props.workOrder?.scheduled_start_date || '',
   scheduled_end_date: props.workOrder?.scheduled_end_date || '',
   notes: props.workOrder?.notes || '',
});

const submitted = ref(false);
const selectedCompany = ref(props.workOrder?.company_id || (props.companies.length > 1 ? null : props.companies[0].id));

watch(selectedCompany, (newCompanyId) => {
   form.company_id = newCompanyId;
   if (!props.workOrder) {
      router.reload({ only: ['branches', 'boms'], data: { company_id: newCompanyId } });
   }
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.workOrder && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

watch(
    () => props.boms,
    (newBoms) => {
        if (!props.workOrder && newBoms.length === 1) {
            form.bom_id = newBoms[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
   selectedCompany.value = props.workOrder?.company_id || (props.companies.length > 1 ? null : props.companies[0].id);
   if (!props.workOrder && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
   if (!props.workOrder && props.boms.length === 1) {
      form.bom_id = props.boms[0].id;
   }
});

function getFinishedProductVariants(bomId) {
   if (!bomId) return [];
   const bom = props.boms.find(b => b.id === bomId);
   return bom && bom.finished_product && bom.finished_product.variants ? bom.finished_product.variants : [];
}

function submitForm() {
   submitted.value = true;
   if (props.workOrder) {
      form.put(route('work-orders.update', props.workOrder.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('work-orders.store'), {
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
                  v-model="selectedCompany"
                  :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                  label="Perusahaan:"
                  placeholder="Pilih Perusahaan"
                  :error="form.errors.company_id"
                  :disabled="!!props.workOrder"
                  required
               />

               <AppSelect
                  v-model="form.branch_id"
                  :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang:"
                  placeholder="Pilih Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.workOrder"
                  required
               />
            </div>

            <div class="grid grid-cols-3 gap-4">
               <AppSelect
                  v-model="form.bom_id"
                  :options="props.boms.map(bom => ({ value: bom.id, label: `${bom.name} - ${bom.finished_product?.name}` }))"
                  label="Bill of Material:"
                  placeholder="Pilih BOM"
                  :error="form.errors.bom_id"
                  :disabled="!!props.workOrder"
                  required
               />

               <AppSelect
                  v-model="form.finished_product_variant_id"
                  :options="getFinishedProductVariants(form.bom_id).map(variant => ({ value: variant.id, label: `${variant.sku} - ${variant.name}` }))"
                  label="Varian Produk Jadi:"
                  placeholder="Pilih Varian (Opsional)"
                  :error="form.errors.finished_product_variant_id"
               />

               <AppSelect
                  v-model="form.wip_location_id"
                  :options="props.locations.map(location => ({ value: location.id, label: location.name }))"
                  label="Lokasi WIP:"
                  placeholder="Pilih Lokasi WIP"
                  :error="form.errors.wip_location_id"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.quantity_planned"
                  type="text"
                  :numberFormat="true"
                  label="Quantity Direncanakan:"
                  :error="form.errors.quantity_planned"
                  required
               />

               <div></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.scheduled_start_date"
                  type="date"
                  label="Tanggal Mulai Direncanakan:"
                  :error="form.errors.scheduled_start_date"
               />

               <AppInput
                  v-model="form.scheduled_end_date"
                  type="date"
                  label="Tanggal Selesai Direncanakan:"
                  :error="form.errors.scheduled_end_date"
               />
            </div>

            <AppTextarea
               v-model="form.notes"
               label="Catatan:"
               :error="form.errors.notes"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Work Order</h3>
            <p class="mb-2">Work Order adalah instruksi untuk memproduksi barang berdasarkan Bill of Material (BOM). Pastikan informasi yang dimasukkan akurat.</p>
            <ul class="list-disc list-inside">
               <li>Pilih perusahaan dan cabang yang sesuai</li>
               <li>Pilih Bill of Material (BOM) yang akan digunakan</li>
               <li>Tentukan quantity yang akan diproduksi</li>
               <li>Atur tanggal mulai dan selesai produksi</li>
               <li>Pilih lokasi WIP (Work In Progress) jika diperlukan</li>
               <li>Work Order dapat berubah status dari Draft → Released → In Progress → Completed</li>
            </ul>
         </div>
      </div>

      <!-- BOM Preview -->
      <div v-if="form.bom_id" class="mt-6">
         <h4 class="text-lg font-semibold mb-2">Preview Bill of Material</h4>
         <div v-for="bom in props.boms.filter(b => b.id === form.bom_id)" :key="bom.id" class="bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-3 gap-4 mb-4">
               <div>
                  <p class="font-semibold">Produk Jadi:</p>
                  <p>{{ bom.finished_product?.name }}</p>
                  <p v-if="bom.finished_product_variant" class="text-sm text-gray-600 mt-1">
                     Varian: {{ bom.finished_product_variant.name }} ({{ bom.finished_product_variant.sku }})
                  </p>
               </div>
               <div>
                  <p class="font-semibold">Quantity per BOM:</p>
                  <p>{{ formatNumber(bom.finished_quantity) }} {{ bom.finished_uom?.name }}</p>
               </div>
               <div>
                  <p class="font-semibold">Total Komponen:</p>
                  <p>{{ bom.bom_lines?.length || 0 }} item(s)</p>
               </div>
            </div>

            <div class="overflow-x-auto">
               <table class="min-w-full bg-white border border-gray-300 text-sm">
                  <thead>
                     <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-2 py-1">No.</th>
                        <th class="border border-gray-300 px-2 py-1">Komponen</th>
                        <th class="border border-gray-300 px-2 py-1">Qty per Unit</th>
                        <th class="border border-gray-300 px-2 py-1">Total Qty</th>
                        <th class="border border-gray-300 px-2 py-1">Satuan</th>
                        <th class="border border-gray-300 px-2 py-1">Backflush</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr v-for="line in bom.bom_lines" :key="line.id">
                        <td class="border border-gray-300 px-2 py-1">{{ line.line_number }}</td>
                        <td class="border border-gray-300 px-2 py-1">{{ line.component_product?.name }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-right">{{ formatNumber(line.quantity_per) }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-right">{{ formatNumber(line.quantity_per * form.quantity_planned) }}</td>
                        <td class="border border-gray-300 px-2 py-1">{{ line.uom?.name }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-center">
                           <span v-if="line.backflush" class="text-green-600">✓</span>
                           <span v-else class="text-gray-400">✗</span>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
         </div>
      </div>

      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit">
            {{ props.workOrder ? 'Ubah' : 'Tambah' }} Work Order
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('work-orders.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
