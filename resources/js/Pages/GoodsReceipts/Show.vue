<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';
import { computed } from 'vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppPrintButton from '@/Components/AppPrintButton.vue';

const props = defineProps({
   goodsReceipt: Object,
   filters: Object,
});

const canCreateReturn = computed(() => Number(props.goodsReceipt?.returnable_quantity || 0) > 0);

const linkedPurchaseOrders = computed(() => {
   const pos = props.goodsReceipt?.purchase_orders || [];
   if (pos.length === 0 && props.goodsReceipt?.purchase_order) {
      return [props.goodsReceipt.purchase_order];
   }
   return pos;
});

const supplierName = computed(() => {
   if (linkedPurchaseOrders.value.length > 0) {
      return linkedPurchaseOrders.value[0]?.partner?.name || '—';
   }
   return props.goodsReceipt?.purchase_order?.partner?.name || '—';
});
</script>

<template>
   <Head :title="`Penerimaan Pembelian ${goodsReceipt.receipt_number}`" />

   <AuthenticatedLayout>
      <template #header>
         <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div>
               <p class="text-sm text-gray-500">Penerimaan Pembelian</p>
               <h2 class="text-2xl font-semibold">
                  {{ goodsReceipt.receipt_number }}                    
               </h2>
            </div>
            <DocumentStatusPill
               :documentKind="DocumentStatusKind.GOODS_RECEIPT"
               :status="goodsReceipt.status"
            />
         </div>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="space-y-6">
                     <div class="flex justify-between items-center mb-4">
                        <AppBackLink :href="route('goods-receipts.index', filters)" text="Kembali ke Daftar Penerimaan" />
                        <div class="flex flex-wrap">
                           <a :href="route('goods-receipts.print', goodsReceipt.id)" target="_blank">
                              <AppPrintButton title="Print" />
                           </a>
                           <Link :href="route('goods-receipts.edit', goodsReceipt.id)">
                              <AppEditButton title="Edit" />
                           </Link>
                           <Link
                              v-if="canCreateReturn"
                              :href="route('purchase-returns.create', { goods_receipt_id: goodsReceipt.id })"
                              class="ml-3"
                           >
                              <AppSecondaryButton>Buat Retur</AppSecondaryButton>
                           </Link>
                        </div>
                     </div>
                  </div>

                  <div class="grid grid-cols-3 gap-4 *:py-1 text-sm mb-6">
                     <div>
                        <p class="font-semibold">Tanggal:</p>
                        <p>{{ goodsReceipt.receipt_date ? new Date(goodsReceipt.receipt_date).toLocaleDateString('id-ID') : '—' }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Lokasi:</p>
                        <p>{{ goodsReceipt.location?.name || '—' }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Metode Penilaian:</p>
                        <p>{{ goodsReceipt.valuation_method?.toUpperCase() || '—' }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Supplier:</p>
                        <p>{{ supplierName }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Total Qty:</p>
                        <p>{{ formatNumber(goodsReceipt.total_quantity, 3) }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Diposting Pada:</p>
                        <p>{{ goodsReceipt.posted_at || '—' }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Purchase Order:</p>
                        <div v-if="linkedPurchaseOrders.length > 0" class="flex flex-wrap gap-1 mt-1">
                           <Link
                              v-for="po in linkedPurchaseOrders"
                              :key="po.id"
                              :href="route('purchase-orders.show', po.id)"
                              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200"
                           >
                              {{ po.order_number }}
                           </Link>
                        </div>
                        <span v-else>—</span>
                     </div>
                     <div>
                        <p class="font-semibold">Transaksi Persediaan:</p>
                        <p>{{ goodsReceipt.inventory_transaction?.transaction_number || '—' }}</p>
                     </div>
                  </div>

                  <div class="mt-6">
                     <h4 class="text-lg font-semibold mb-2">Detail Barang</h4>
                     <table class="w-full border-collapse border border-gray-300 text-sm">
                        <thead>
                           <tr>
                              <th class="bg-gray-100 border border-gray-300 px-4 py-2">Produk</th>
                              <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Qty</th>
                              <th class="bg-gray-100 border border-gray-300 px-4 py-2">Satuan</th>
                              <th class="bg-gray-100 border border-gray-300 px-4 py-2">Lot</th>
                              <th class="bg-gray-100 border border-gray-300 px-4 py-2">Serial</th>
                           </tr>
                        </thead>
                        <tbody>
                           <tr v-for="line in goodsReceipt.lines" :key="line.id" class="group">
                              <td class="border border-gray-300 px-4 py-2">
                                 <div class="font-medium">{{ line.variant?.product_name || line.description }}</div>
                                 <div class="text-xs text-gray-500">{{ line.variant?.sku }}</div>
                              </td>
                              <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity, 3) }}</td>
                              <td class="border border-gray-300 px-4 py-2">{{ line.uom?.code || '—' }}</td>
                              <td class="border border-gray-300 px-4 py-2">
                                 {{ line.lot?.lot_code || '—' }}
                                 <div v-if="line.lot?.expiry_date" class="text-xs text-gray-500">
                                    Expire: {{ new Date(line.lot.expiry_date).toLocaleDateString('id-ID') }}
                                 </div>
                              </td>
                              <td class="border border-gray-300 px-4 py-2">{{ line.serial?.serial_no || '—' }}</td>
                           </tr>
                        </tbody>
                        <tfoot>
                           <tr>
                              <td class="border border-gray-300 px-4 py-2 font-semibold">Total</td>
                              <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(goodsReceipt.total_quantity, 3) }}</td>
                              <td class="border border-gray-300 px-4 py-2"></td>
                              <td class="border border-gray-300 px-4 py-2"></td>
                              <td class="border border-gray-300 px-4 py-2"></td>
                           </tr>
                        </tfoot>
                     </table>
                  </div>

                  <div v-if="goodsReceipt.notes" class="mt-6">
                     <h4 class="text-lg font-semibold mb-2">Catatan</h4>
                     <p class="text-gray-700 whitespace-pre-line">{{ goodsReceipt.notes }}</p>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </AuthenticatedLayout>
</template>
