<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, watch, onMounted, nextTick } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
   salesReturn: Object,
   salesDeliveries: Array,
   selectedSalesDelivery: Object,
   filters: Object,
   reasonOptions: Array,
});

const form = useForm({
   sales_delivery_id: props.selectedSalesDelivery?.id || null,
   return_date: new Date().toISOString().split('T')[0],
   reason_code: '',
   notes: '',
   lines: props.selectedSalesDelivery?.lines?.map(line => ({
      sales_delivery_line_id: line.id,
      quantity: Math.min(line.available_quantity, line.ordered_quantity),
      unit_price: line.unit_price,
      unit_cost_base: line.unit_cost_base,
   })) || [],
});

const submitted = ref(false);
const selectedDelivery = ref(props.selectedSalesDelivery);

watch(() => props.selectedSalesDelivery, (newDelivery) => {
   if (newDelivery) {
      selectedDelivery.value = newDelivery;
      form.sales_delivery_id = newDelivery.id;
      form.lines = newDelivery.lines?.map(line => ({
         sales_delivery_line_id: line.id,
         quantity: Math.min(line.available_quantity, line.ordered_quantity),
         unit_price: line.unit_price,
         unit_cost_base: line.unit_cost_base,
      })) || [];
   } else {
      selectedDelivery.value = null;
      form.lines = [];
   }
}, { immediate: true });

// Watch for delivery selection changes
watch(() => form.sales_delivery_id, (newDeliveryId) => {
   if (newDeliveryId) {
      // Reload the page to get the full delivery details
      router.get(route('sales-returns.create'), {
         sales_delivery_id: newDeliveryId,
      }, {
         preserveState: false,
         preserveScroll: true,
      });
   } else {
      // Clear selection
      router.get(route('sales-returns.create'));
   }
});

function addLine() {
   if (selectedDelivery.value?.lines?.length > 0) {
      const availableLines = selectedDelivery.value.lines.filter(deliveryLine =>
         !form.lines.some(line => line.sales_delivery_line_id === deliveryLine.id)
      );

      if (availableLines.length > 0) {
         const line = availableLines[0];
         form.lines.push({
            sales_delivery_line_id: line.id,
            quantity: Math.min(line.available_quantity, line.ordered_quantity),
            unit_price: line.unit_price,
            unit_cost_base: line.unit_cost_base,
         });
      }
   }
}

function removeLine(index) {
   form.lines.splice(index, 1);
}

function submitForm() {
   submitted.value = true;
   form.post(route('sales-returns.store'), {
      preserveScroll: true,
      onSuccess: () => {
         submitted.value = false;
      },
      onError: () => {
         submitted.value = false;
      }
   });
}

function getDeliveryLine(deliveryLineId) {
   return selectedDelivery.value?.lines?.find(line => line.id === deliveryLineId);
}

function getLineTotal(index) {
   const line = form.lines[index];
   const deliveryLine = getDeliveryLine(line.sales_delivery_line_id);
   if (!deliveryLine) return 0;

   return line.quantity * deliveryLine.unit_price;
}
</script>

<template>
   <form @submit.prevent="submitForm" class="space-y-4">
      <div class="flex justify-between">
         <div class="w-2/3 max-w-2xl mr-8">
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.sales_delivery_id"
                  :options="props.salesDeliveries.map(delivery => ({ value: delivery.id, label: delivery.delivery_number }))"
                  label="Pengiriman Penjualan:"
                  placeholder="Pilih Pengiriman Penjualan"
                  :error="form.errors.sales_delivery_id"
                  required
               />

               <AppInput
                  v-model="form.return_date"
                  type="date"
                  label="Tanggal Retur:"
                  :error="form.errors.return_date"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.reason_code"
                  :options="props.reasonOptions"
                  label="Alasan Retur:"
                  placeholder="Pilih Alasan"
                  :error="form.errors.reason_code"
               />

               <div></div>
            </div>

            <AppTextarea
               v-model="form.notes"
               label="Catatan:"
               :error="form.errors.notes"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Retur Penjualan</h3>
            <p class="mb-2">Retur penjualan digunakan untuk mengembalikan barang yang dikirim ke customer. Pastikan informasi yang dimasukkan akurat.</p>
            <ul class="list-disc list-inside">
               <li>Pilih pengiriman penjualan yang akan diretur</li>
               <li>Tentukan tanggal retur</li>
               <li>Pilih alasan retur yang sesuai</li>
               <li>Tentukan jumlah yang akan diretur untuk setiap baris</li>
            </ul>
         </div>
      </div>

      <div v-if="selectedDelivery" class="overflow-x-auto">
         <table class="min-w-full bg-white border border-gray-300">
            <thead>
               <tr class="bg-gray-100">
                  <th class="border border-gray-300 text-sm min-w-48 lg:min-w-72 px-1.5 py-1.5">Produk</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Qty Retur</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Satuan</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Harga</th>
                  <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Total</th>
                  <th class="border border-gray-300 px-1.5 py-1.5"></th>
               </tr>
            </thead>
            <tbody>
               <tr v-for="(line, index) in form.lines" :key="index">
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <div class="text-sm">
                        <div class="font-medium">{{ getDeliveryLine(line.sales_delivery_line_id)?.variant?.product_name }}</div>
                        <div class="text-gray-500">{{ getDeliveryLine(line.sales_delivery_line_id)?.variant?.sku }}</div>
                     </div>
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5">
                     <AppInput
                        v-model="line.quantity"
                        :numberFormat="true"
                        :error="form.errors[`lines.${index}.quantity`]"
                        :max="getDeliveryLine(line.sales_delivery_line_id)?.available_quantity"
                        required
                        :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                     />
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-center">
                     {{ getDeliveryLine(line.sales_delivery_line_id)?.uom?.code }}
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-right">
                     {{ formatNumber(getDeliveryLine(line.sales_delivery_line_id)?.unit_price || 0) }}
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-right">
                     {{ formatNumber(getLineTotal(index)) }}
                  </td>
                  <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                     <button type="button" @click="removeLine(index)" class="text-red-500 hover:text-red-700">
                        <TrashIcon class="w-5 h-5" />
                     </button>
                  </td>
               </tr>
            </tbody>

            <tfoot>
               <tr class="text-sm">
                  <th class="border border-gray-300 px-4 py-2 text-right" colspan="4">Total</th>
                  <th class="border border-gray-300 px-4 py-2 text-left">{{ formatNumber(form.lines.reduce((sum, line) => sum + getLineTotal(form.lines.indexOf(line)), 0)) }}</th>
                  <th></th>
               </tr>
            </tfoot>
         </table>
         <div class="flex mt-2 mb-4">
            <button type="button" @click="addLine" class="flex items-center text-main-500 hover:text-main-700" :disabled="!selectedDelivery || form.lines.length >= (selectedDelivery.lines?.length || 0)">
               <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Baris
            </button>
         </div>
      </div>

      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit">
            Buat Retur Penjualan
         </AppPrimaryButton>
         <AppSecondaryButton @click="$inertia.visit(route('sales-returns.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template>
