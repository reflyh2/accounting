<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
   role: Object,
   filters: Object,
});

const form = useForm({
   name: props.role?.name ?? '',
   description: props.role?.description ?? '',
   access_level: props.role?.access_level ?? null,
});

const submitted = ref(false);

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   if (props.role) {
      form.put(route('roles.update', props.role.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('roles.store', { create_another: createAnother }), {
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
      <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-2xl mr-8">
         <AppInput
            v-model="form.name"
            label="Nama Hak Akses:"
            placeholder="Masukkan nama hak akses"
            :error="form.errors.name"
            autofocus
            required
            :submitted="submitted"
         />
         <AppSelect
            v-model="form.access_level"
            label="Tingkat Akses:"
            :options="[
                { value: 'own', label: 'Data Sendiri' },
                { value: 'branch', label: 'Cabang' },
                { value: 'branch_group', label: 'Kelompok Cabang' },
                { value: 'company', label: 'Perusahaan' }
            ]"
            placeholder="Pilih tingkat akses"
            :error="form.errors.access_level"
            required
            :submitted="submitted"
         />
         <AppTextarea
            v-model="form.description"
            label="Deskripsi:"
            placeholder="Masukkan deskripsi hak akses"
            :error="form.errors.description"
            :submitted="submitted"
         />
         <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
               {{ props.role ? 'Ubah' : 'Tambah' }} Hak Akses
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.role" type="button" @click="submitForm(true)" class="mr-2">
               Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('roles.index', filters))">
               Batal
            </AppSecondaryButton>
         </div>
      </form>
      
      <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
         <h3 class="text-lg font-semibold mb-2">Informasi Hak Akses</h3>
         <p class="mb-2">Hak akses menentukan apa yang dapat dilakukan oleh pengguna dalam sistem. Pastikan untuk memberikan deskripsi yang jelas.</p>
         <ul class="list-disc list-inside">
            <li>Gunakan nama yang deskriptif</li>
            <li>Jelaskan fungsi hak akses dalam deskripsi</li>
            <li>Pertimbangkan implikasi keamanan</li>
         </ul>
      </div>
   </div>
</template>