<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
   branch: Object,
   branchGroups: Array,
   companies: Array, // Add this prop
   filters: Object,
});

const form = useForm({
   name: props.branch?.name || '',
   address: props.branch?.address || '',
   branch_group_id: props.branch?.branch_group_id || null,
   create_another: false,
});

const selectedCompany = ref(props.branch?.branch_group?.company?.id || null);
const filteredBranchGroups = ref(props.branchGroups);

const submitted = ref(false);

watch(selectedCompany, (newCompany) => {
   if (newCompany) {
      filteredBranchGroups.value = props.branchGroups.filter(group => group.company_id === newCompany);
   } else {
      filteredBranchGroups.value = props.branchGroups;
   }
   form.branch_group_id = null;
});

function resetForm() {
   form.reset();
   form.clearErrors();
}

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   if (props.branch) {
      form.put(route('branches.update', props.branch.id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('branches.store'), {
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
         <AppSelect
            v-model="selectedCompany"
            label="Perusahaan:"
            :options="companies.map(company => ({ value: company.id, label: company.name }))"
            placeholder="Pilih perusahaan"
         />
         <AppInput
            v-model="form.name"
            label="Nama Cabang:"
            placeholder="Masukkan nama cabang"
            :error="form.errors.name"
            autofocus
            required
         />
         <AppInput
            v-model="form.address"
            label="Alamat:"
            placeholder="Masukkan alamat cabang"
            :error="form.errors.address"
            required
         />
         <AppSelect
            v-model="form.branch_group_id"
            label="Kelompok Cabang:"
            :options="filteredBranchGroups.map(group => ({ value: group.id, label: group.name }))"
            placeholder="Pilih kelompok cabang"
            :error="form.errors.branch_group_id"
            required
         />
         <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
               {{ props.branch ? 'Ubah' : 'Tambah' }} Cabang
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.branch" type="button" @click="submitForm(true)" class="mr-2">
               Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('branches.index', filters))">
               Batal
            </AppSecondaryButton>
         </div>
      </form>
      
      <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
         <h3 class="text-lg font-semibold mb-2">Informasi Cabang</h3>
         <p class="mb-2">Cabang adalah lokasi fisik dari bisnis Anda. Informasi yang akurat membantu dalam manajemen dan pelaporan.</p>
         <ul class="list-disc list-inside">
            <li>Pastikan nama cabang unik</li>
            <li>Alamat harus lengkap dan jelas</li>
            <li>Pilih kelompok cabang yang sesuai</li>
         </ul>
      </div>
   </div>
</template>