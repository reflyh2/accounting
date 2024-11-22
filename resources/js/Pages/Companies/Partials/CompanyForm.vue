<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
   company: Object,
   filters: Object,
});

const form = useForm({
   name: props.company?.name || '',
   legal_name: props.company?.legal_name || '',
   tax_id: props.company?.tax_id || '',
   business_registration_number: props.company?.business_registration_number || '',
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
   create_another: false,
});

const submitted = ref(false);

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   if (props.company) {
      form.put(route('companies.update', props.company.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('companies.store'), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
            if (createAnother) {
               form.reset();
               form.clearErrors();
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
   <div class="flex justify-between">
      <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-xl mr-8">
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
         </ul>
      </div>
   </div>
</template>