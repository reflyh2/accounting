<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
   asset: Object,
   companies: Array,
   branches: Array,
   categories: Array,
   filters: Object,
   assetTypes: Object,
   acquisitionTypes: Object,
   depreciationMethods: Object,
   statusOptions: Object,
});

const form = useForm({
   company_id: props.asset?.company_id || null,
   branch_id: props.asset?.branch_id || null,
   asset_category_id: props.asset?.asset_category_id || null,
   name: props.asset?.name || '',
   type: props.asset?.type || 'tangible',
   acquisition_type: props.asset?.acquisition_type || 'outright_purchase',
   acquisition_date: props.asset?.acquisition_date || new Date().toISOString().split('T')[0],
   cost_basis: props.asset?.cost_basis || 0,
   salvage_value: props.asset?.salvage_value || 0,
   is_depreciable: props.asset?.is_depreciable ?? true,
   is_amortizable: props.asset?.is_amortizable ?? false,
   depreciation_method: props.asset?.depreciation_method || 'straight-line',
   useful_life_months: props.asset?.useful_life_months || 60,
   depreciation_start_date: props.asset?.depreciation_start_date || new Date().toISOString().split('T')[0],
   accumulated_depreciation: props.asset?.accumulated_depreciation || 0,
   net_book_value: props.asset?.net_book_value || null,
   status: props.asset?.status || 'active',
   notes: props.asset?.notes || '',
   warranty_expiry: props.asset?.warranty_expiry || null,
   create_another: false,
});

const submitted = ref(false);
const selectedCompany = ref(props.asset?.company_id || (props.companies.length === 1 ? props.companies[0].id : null));

// Calculate net book value whenever cost_basis or accumulated_depreciation changes
const netBookValue = computed(() => {
   const cost = parseFloat(form.cost_basis) || 0;
   const accumulatedDepreciation = parseFloat(form.accumulated_depreciation) || 0;
   return cost - accumulatedDepreciation;
});

watch(() => [form.cost_basis, form.accumulated_depreciation], () => {
   form.net_book_value = netBookValue.value;
}, { deep: true });

watch(selectedCompany, (newCompanyId) => {
   if (!props.asset && newCompanyId) {
      form.company_id = newCompanyId;
      router.reload({ only: ['branches'], data: { company_id: newCompanyId } });
   }
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.asset && newBranches && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
   selectedCompany.value = props.asset?.company_id || (props.companies.length === 1 ? props.companies[0].id : null);
   if (!props.asset && props.branches && props.branches.length === 1) {
      form.branch_id = props.branches[0].id;
   }
});

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   
   if (props.asset) {
      form.put(route('assets.update', props.asset.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('assets.store'), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
            if (createAnother) {
               form.reset();
               form.clearErrors();
               form.company_id = selectedCompany.value;
            }
         },
         onError: () => {
            submitted.value = false;
         }
      });
   }
}
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
                  :disabled="!!props.asset"
                  required
               />
               
               <AppSelect
                  v-model="form.branch_id"
                  :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                  label="Cabang:"
                  placeholder="Pilih Cabang"
                  :error="form.errors.branch_id"
                  :disabled="!!props.asset"
                  required
               />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.asset_category_id"
                  :options="props.categories.map(category => ({ value: category.id, label: category.name }))"
                  label="Kategori Aset:"
                  placeholder="Pilih Kategori"
                  :error="form.errors.asset_category_id"
                  required
               />
               
               <AppInput
                  v-model="form.name"
                  label="Nama Aset:"
                  :error="form.errors.name"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.type"
                  :options="Object.entries(props.assetTypes).map(([value, label]) => ({ value, label }))"
                  label="Jenis Aset:"
                  :error="form.errors.type"
                  required
               />
               
               <AppSelect
                  v-model="form.acquisition_type"
                  :options="Object.entries(props.acquisitionTypes).map(([value, label]) => ({ value, label }))"
                  label="Cara Perolehan:"
                  :error="form.errors.acquisition_type"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.acquisition_date"
                  type="date"
                  label="Tanggal Perolehan:"
                  :error="form.errors.acquisition_date"
               />
               
               <AppInput
                  v-model="form.cost_basis"
                  :numberFormat="true"
                  label="Nilai Perolehan:"
                  :error="form.errors.cost_basis"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.salvage_value"
                  :numberFormat="true"
                  label="Nilai Residu:"
                  :error="form.errors.salvage_value"
               />
               
               <AppSelect
                  v-model="form.status"
                  :options="Object.entries(props.statusOptions).map(([value, label]) => ({ value, label }))"
                  label="Status:"
                  :error="form.errors.status"
                  required
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <div class="flex flex-col">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Dapat Disusutkan:</label>
                  <div class="flex items-center mt-2">
                     <input 
                        type="checkbox" 
                        v-model="form.is_depreciable" 
                        class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
                     />
                     <span class="ml-2 text-sm text-gray-600">{{ form.is_depreciable ? 'Ya' : 'Tidak' }}</span>
                  </div>
                  <p v-if="form.errors.is_depreciable" class="mt-1 text-sm text-red-600">{{ form.errors.is_depreciable }}</p>
               </div>
               
               <div class="flex flex-col">
                  <label class="mb-1 block text-sm font-medium text-gray-700">Dapat Diamortisasi:</label>
                  <div class="flex items-center mt-2">
                     <input 
                        type="checkbox" 
                        v-model="form.is_amortizable" 
                        class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
                     />
                     <span class="ml-2 text-sm text-gray-600">{{ form.is_amortizable ? 'Ya' : 'Tidak' }}</span>
                  </div>
                  <p v-if="form.errors.is_amortizable" class="mt-1 text-sm text-red-600">{{ form.errors.is_amortizable }}</p>
               </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppSelect
                  v-model="form.depreciation_method"
                  :options="Object.entries(props.depreciationMethods).map(([value, label]) => ({ value, label }))"
                  label="Metode Penyusutan:"
                  :error="form.errors.depreciation_method"
                  required
               />
               
               <AppInput
                  v-model="form.useful_life_months"
                  type="number"
                  label="Umur Ekonomis (Bulan):"
                  :error="form.errors.useful_life_months"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.depreciation_start_date"
                  type="date"
                  label="Tanggal Mulai Penyusutan:"
                  :error="form.errors.depreciation_start_date"
               />
               
               <AppInput
                  v-model="form.accumulated_depreciation"
                  :numberFormat="true"
                  label="Akumulasi Penyusutan:"
                  :error="form.errors.accumulated_depreciation"
               />
            </div>

            <div class="grid grid-cols-2 gap-4">
               <AppInput
                  v-model="form.net_book_value"
                  :numberFormat="true"
                  label="Nilai Buku:"
                  :error="form.errors.net_book_value"
                  disabled
               />
               
               <AppInput
                  v-model="form.warranty_expiry"
                  type="date"
                  label="Kadaluwarsa Garansi:"
                  :error="form.errors.warranty_expiry"
               />
            </div>
            
            <AppTextarea
               v-model="form.notes"
               label="Catatan:"
               :error="form.errors.notes"
            />
         </div>

         <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
            <h3 class="text-lg font-semibold mb-2">Informasi Aset</h3>
            <p class="mb-2">Aset adalah sumber daya yang dimiliki perusahaan yang diharapkan memberikan manfaat ekonomi di masa depan.</p>
            <ul class="list-disc list-inside">
               <li>Aset berwujud adalah aset yang memiliki bentuk fisik (contoh: bangunan, kendaraan, mesin)</li>
               <li>Aset tidak berwujud adalah aset yang tidak memiliki bentuk fisik (contoh: software, hak paten, goodwill)</li>
               <li>Nilai buku = Nilai perolehan - Akumulasi penyusutan</li>
               <li>Penyusutan adalah alokasi sistematis dari nilai aset yang dapat disusutkan selama umur manfaatnya</li>
               <li>Amortisasi adalah alokasi sistematis dari nilai aset tidak berwujud selama umur manfaatnya</li>
            </ul>
         </div>
      </div>
      
      <div class="mt-4 flex items-center">
         <AppPrimaryButton type="submit" class="mr-2">
            {{ props.asset ? 'Ubah' : 'Tambah' }} Aset
         </AppPrimaryButton>
         <AppUtilityButton v-if="!props.asset" type="button" @click="submitForm(true)" class="mr-2">
            Tambah & Buat Lagi
         </AppUtilityButton>
         <AppSecondaryButton @click="$inertia.visit(route('assets.index', filters))">
            Batal
         </AppSecondaryButton>
      </div>
   </form>
</template> 