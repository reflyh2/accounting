<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';
import { WrenchScrewdriverIcon } from '@heroicons/vue/24/solid';
import { statusOptions, getStatusClass } from '@/constants/assetStatus';

const props = defineProps({
    asset: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAsset = () => {
    form.delete(route('assets.destroy', props.asset.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
   <Head title="Detail Aset" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Detail Aset</h2>
      </template>

      <div>
         <div class="min-w-min md:min-w-max mx-auto">
               <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                  <div class="p-6 text-gray-900">
                     <div class="mb-6">
                           <AppBackLink :href="route('assets.index', filters)" text="Kembali ke Daftar Aset" />
                     </div>
                     <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">{{ asset.name }}</h3>
                        <div class="flex items-center">
                           <Link
                              :href="route('asset-maintenance.index', asset.id)"
                              class="inline-flex items-center justify-center align-middle w-4 h-4 md:ml-3 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50"
                           >
                              <WrenchScrewdriverIcon class="h-4 w-4" />
                           </Link>
                           <Link :href="route('assets.edit', asset.id)">
                              <AppEditButton title="Edit" />
                           </Link>
                           <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                        </div>
                     </div>
                     <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                           <div>
                              <p class="font-semibold">Perusahaan:</p>
                              <p>{{ asset.branch.branch_group.company.name }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Cabang:</p>
                              <p>{{ asset.branch.name }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Kategori:</p>
                              <p>{{ asset.category.name }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Status:</p>
                              <p><span :class="getStatusClass(asset.status)">{{ statusOptions.find(option => option.value === asset.status)?.label || asset.status }}</span></p>
                           </div>
                           <div>
                              <p class="font-semibold">Nomor Seri:</p>
                              <p>{{ asset.serial_number || '-' }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Supplier:</p>
                              <p>{{ asset.supplier || '-' }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Tanggal Pembelian:</p>
                              <p>{{ new Date(asset.purchase_date).toLocaleDateString() }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Harga Pembelian:</p>
                              <p>{{ formatNumber(asset.purchase_cost) }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Nilai Sisa:</p>
                              <p>{{ formatNumber(asset.salvage_value) }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Nilai Sekarang:</p>
                              <p>{{ formatNumber(asset.current_value) }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Metode Penyusutan:</p>
                              <p>{{ asset.depreciation_method === 'straight-line' ? 'Garis Lurus' : 'Saldo Menurun' }}</p>
                           </div>
                           <div>
                              <p class="font-semibold">Masa Manfaat:</p>
                              <p>{{ asset.useful_life_months }} bulan</p>
                           </div>
                           <div>
                              <p class="font-semibold">Garansi Berakhir:</p>
                              <p>{{ asset.warranty_expiry ? new Date(asset.warranty_expiry).toLocaleDateString() : '-' }}</p>
                           </div>
                           <div class="col-span-2">
                              <p class="font-semibold">Catatan:</p>
                              <p>{{ asset.notes || '-' }}</p>
                           </div>
                     </div>

                     <div class="mt-6">
                           <div class="flex justify-between items-center mb-4">
                              <h4 class="text-lg font-semibold">Riwayat Pemeliharaan</h4>
                           </div>
                           <table v-if="asset.maintenance_records?.length" class="w-full border-collapse border border-gray-300 text-sm">
                              <thead>
                                 <tr class="bg-gray-100">
                                       <th class="border border-gray-300 px-4 py-2">Tanggal</th>
                                       <th class="border border-gray-300 px-4 py-2">Jenis</th>
                                       <th class="border border-gray-300 px-4 py-2">Biaya</th>
                                       <th class="border border-gray-300 px-4 py-2">Status</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <tr v-for="record in asset.maintenance_records" :key="record.id">
                                       <td class="border border-gray-300 px-4 py-2">{{ new Date(record.maintenance_date).toLocaleDateString() }}</td>
                                       <td class="border border-gray-300 px-4 py-2">{{ record.maintenance_type }}</td>
                                       <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(record.cost) }}</td>
                                       <td class="border border-gray-300 px-4 py-2 text-center">
                                          <span :class="record.completed_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                             {{ record.completed_at ? 'Selesai' : 'Dalam Proses' }}
                                          </span>
                                       </td>
                                 </tr>
                              </tbody>
                           </table>
                           <p v-else class="text-gray-500 italic">Belum ada catatan pemeliharaan</p>
                     </div>
                  </div>
               </div>
         </div>
      </div>

      <DeleteConfirmationModal
         :show="showDeleteConfirmation"
         title="Hapus Aset"
         @close="showDeleteConfirmation = false"
         @confirm="deleteAsset"
      />
   </AuthenticatedLayout>
</template> 