<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
   company: Object,
   filters: Object,
   taxJurisdictions: Array,
});

const form = useForm({
   name: props.company?.name || '',
   legal_name: props.company?.legal_name || '',
   tax_id: props.company?.tax_id || '',
   business_registration_number: props.company?.business_registration_number || '',
   default_tax_jurisdiction_id: props.company?.default_tax_jurisdiction_id || '',
   address: props.company?.address || '',
   city: props.company?.city || '',
   province: props.company?.province || '',
   postal_code: props.company?.postal_code || '',
   phone: props.company?.phone || '',
   email: props.company?.email || '',
   website: props.company?.website || '',
   industry: props.company?.industry || '',
   year_established: props.company?.year_established || '',
   business_license_number: props.company?.business_license_number || '',
   business_license_expiry: props.company?.business_license_expiry || '',
   tax_registration_number: props.company?.tax_registration_number || '',
   social_security_number: props.company?.social_security_number || '',
   enable_maker_checker: props.company?.enable_maker_checker || false,
   logo: null,
   create_another: false,
});

const logoPreview = ref(props.company?.logo_url || null);
const submitted = ref(false);

function handleLogoChange(event) {
   const file = event.target.files[0];
   if (file) {
      form.logo = file;
      logoPreview.value = URL.createObjectURL(file);
   }
}

function removeLogo() {
   form.logo = null;
   logoPreview.value = null;
}

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   
   const options = {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
         submitted.value = false;
         if (createAnother && !props.company) {
            form.reset();
            form.clearErrors();
            logoPreview.value = null;
         }
      },
      onError: () => {
         submitted.value = false;
      }
   };

   if (props.company) {
      // For updates with file uploads, we need to use POST with _method spoofing
      form.transform((data) => ({
         ...data,
         _method: 'PUT',
      })).post(route('companies.update', props.company.id), options);
   } else {
      form.post(route('companies.store'), options);
   }
}
</script>

<template>
   <div class="flex justify-between">
      <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-xl mr-8">
         <!-- Logo Upload -->
         <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Logo Perusahaan</label>
            <div class="flex items-start space-x-4">
               <div v-if="logoPreview" class="relative">
                  <img :src="logoPreview" alt="Logo Preview" class="w-32 h-32 object-contain border rounded" />
                  <button
                     type="button"
                     @click="removeLogo"
                     class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                  >
                     ×
                  </button>
               </div>
               <div v-else class="w-32 h-32 border-2 border-dashed border-gray-300 rounded flex items-center justify-center text-gray-400 text-sm">
                  Tidak ada logo
               </div>
               <div class="flex-1">
                  <input
                     type="file"
                     accept="image/jpeg,image/png,image/gif"
                     @change="handleLogoChange"
                     class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                  />
                  <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, GIF. Max: 2MB</p>
                  <p v-if="form.errors.logo" class="text-red-500 text-sm mt-1">{{ form.errors.logo }}</p>
               </div>
            </div>
         </div>

         <AppInput
            v-model="form.name"
            label="Nama Perusahaan:"
            :error="form.errors.name"
            autofocus
            required
         />
         <AppInput
            v-model="form.legal_name"
            label="Nama Resmi:"
            :error="form.errors.legal_name"
         />
         <AppInput
            v-model="form.tax_id"
            label="NPWP:"
            :error="form.errors.tax_id"
         />
         <AppSelect
            v-model="form.default_tax_jurisdiction_id"
            label="Jurisdiksi Pajak:"
            :options="taxJurisdictions.map((jurisdiction) => ({
               value: jurisdiction.id,
               label: jurisdiction.name,
            }))"
            :error="form.errors.default_tax_jurisdiction_id"
         />
         <AppInput
            v-model="form.business_registration_number"
            label="NIB:"
            :error="form.errors.business_registration_number"
         />
         <AppInput
            v-model="form.address"
            label="Alamat:"
            :error="form.errors.address"
            required
         />
         <AppInput
            v-model="form.city"
            label="Kota:"
            :error="form.errors.city"
            required
         />
         <AppInput
            v-model="form.province"
            label="Provinsi:"
            :error="form.errors.province"
            required
         />
         <AppInput
            v-model="form.postal_code"
            label="Kode Pos:"
            :error="form.errors.postal_code"
            required
         />
         <AppInput
            v-model="form.phone"
            label="Telepon:"
            :error="form.errors.phone"
            required
         />
         <AppInput
            v-model="form.email"
            label="Email:"
            :error="form.errors.email"
         />
         <AppInput
            v-model="form.website"
            label="Situs Web:"
            :error="form.errors.website"
         />
         <AppSelect
            v-model="form.industry"
            label="Industri:"
            :options="[
               { value: 'manufacturing', label: 'Manufaktur' },
               { value: 'services', label: 'Jasa' },
               { value: 'retail', label: 'Ritel' },
               { value: 'technology', label: 'Teknologi' },
            ]"
            :error="form.errors.industry"
         />
         <AppInput
            v-model="form.year_established"
            label="Tahun Berdiri:"
            type="number"
            :error="form.errors.year_established"
         />
         <AppInput
            v-model="form.business_license_number"
            label="Nomor Izin Usaha:"
            :error="form.errors.business_license_number"
         />
         <AppInput
            v-model="form.business_license_expiry"
            label="Tanggal Kadaluarsa Izin Usaha:"
            type="date"
            :error="form.errors.business_license_expiry"
         />
         <AppInput
            v-model="form.tax_registration_number"
            label="Nomor Registrasi Pajak:"
            :error="form.errors.tax_registration_number"
         />
         <AppInput
            v-model="form.social_security_number"
            label="Nomor BPJS:"
            :error="form.errors.social_security_number"
         />
         
         <!-- Security Settings Section -->
         <div class="mt-6 mb-4 border-t pt-4">
            <h4 class="font-medium text-gray-700 mb-3">Pengaturan Keamanan</h4>
            <AppCheckbox
               v-model="form.enable_maker_checker"
               label="Aktifkan Maker-Checker (Pembuat dokumen tidak bisa menyetujui dokumen yang sama)"
               :error="form.errors.enable_maker_checker"
            />
         </div>

         <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
               {{ props.company ? 'Ubah' : 'Tambah' }} Perusahaan
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.company" type="button" @click="submitForm(true)" class="mr-2">
               Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('companies.index', filters))">
               Batal
            </AppSecondaryButton>
         </div>
      </form>
      
      <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
         <h3 class="text-lg font-semibold mb-2">Informasi Perusahaan</h3>
         <p class="mb-2">Informasi perusahaan yang akurat penting untuk manajemen dan pelaporan yang efektif.</p>
         <ul class="list-disc list-inside">
            <li>Pastikan nama perusahaan sesuai dengan dokumen resmi</li>
            <li>NPWP dan NIB harus valid dan terdaftar</li>
            <li>Alamat harus lengkap dan sesuai dengan dokumen resmi</li>
            <li>Nomor izin usaha dan tanggal kadaluarsa harus akurat</li>
            <li>Logo akan digunakan pada dokumen cetak (SO, DO, Invoice)</li>
         </ul>
      </div>
   </div>
</template>