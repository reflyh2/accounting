<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
   location: Object,
   branches: Array,
   filters: Object,
});

const form = useForm({
   code: props.location?.code || '',
   name: props.location?.name || '',
   type: props.location?.type || null,
   branch_id: props.location?.branch_id || null,
   is_active: props.location?.is_active ?? true,
   create_another: false,
});

const typeOptions = [
    { value: 'warehouse', label: 'Warehouse' },
    { value: 'store', label: 'Store' },
    { value: 'room', label: 'Room' },
    { value: 'yard', label: 'Yard' },
    { value: 'vehicle', label: 'Vehicle' },
];

const submitted = ref(false);

function resetForm() {
   form.reset();
   form.clearErrors();
}

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   if (props.location) {
      form.put(route('locations.update', props.location.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('locations.store'), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
            if (createAnother) {
               resetForm();
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
      <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-2xl mr-8">
         <AppInput
            v-model="form.code"
            label="Kode:"
            placeholder="Masukkan kode lokasi"
            :error="form.errors.code"
            autofocus
            required
         />
         <AppInput
            v-model="form.name"
            label="Nama Lokasi:"
            placeholder="Masukkan nama lokasi"
            :error="form.errors.name"
            required
         />
         <AppSelect
            v-model="form.type"
            label="Tipe:"
            :options="typeOptions"
            placeholder="Pilih tipe lokasi"
            :error="form.errors.type"
            required
         />
         <AppSelect
            v-model="form.branch_id"
            label="Cabang:"
            :options="branches.map(branch => ({ value: branch.id, label: branch.name }))"
            placeholder="Pilih cabang"
            :error="form.errors.branch_id"
            required
         />
         <div class="mt-4">
            <AppCheckbox
               v-model="form.is_active"
               label="Aktif"
               :error="form.errors.is_active"
            />
         </div>
         <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
               {{ props.location ? 'Ubah' : 'Tambah' }} Lokasi
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.location" type="button" @click="submitForm(true)" class="mr-2">
               Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('locations.index', filters))">
               Batal
            </AppSecondaryButton>
         </div>
      </form>

      <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
         <h3 class="text-lg font-semibold mb-2">Informasi Lokasi</h3>
         <p class="mb-2">Lokasi adalah tempat penyimpanan fisik dalam cabang bisnis Anda. Informasi yang akurat membantu dalam manajemen inventaris.</p>
         <ul class="list-disc list-inside">
            <li>Kode lokasi harus unik</li>
            <li>Pilih tipe lokasi yang sesuai</li>
            <li>Pastikan cabang yang dipilih sudah benar</li>
         </ul>
      </div>
   </div>
</template>
