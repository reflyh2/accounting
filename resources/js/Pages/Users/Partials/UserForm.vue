<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';

const props = defineProps({
   user: Object,
   roles: Array,
   branches: Array,
   companies: Array,
   filters: Object,
});

const form = useForm({
   name: props.user?.name || '',
   email: props.user?.email || '',
   password: '',
   password_confirmation: '',
   roles: props.user?.roles.map(role => role.id) || [],
   branches: props.user?.branches.map(branch => branch.id) || [],
   create_another: false,
});

const selectedCompanies = ref(props.user?.branches.map(branch => branch.branch_group.company.id) || []);
const filteredBranches = ref(props.branches);

watch(selectedCompanies, (newCompanies) => {
   if (newCompanies) {
      filteredBranches.value = props.branches.filter(branch => newCompanies.includes(branch.branch_group.company_id));
   } else {
      filteredBranches.value = props.branches;
   }
   form.branches = [];
});

const submitted = ref(false);

function submitForm(createAnother = false) {
   submitted.value = true;
   form.create_another = createAnother;
   if (props.user) {
      form.put(route('users.update', props.user.global_id), {
         preserveScroll: true,
         onSuccess: () => {
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   } else {
      form.post(route('users.store'), {
         preserveScroll: true,
         onSuccess: () => {
            if (createAnother) {
               resetForm();
            }
            submitted.value = false;
         },
         onError: () => {
            submitted.value = false;
         }
      });
   }
}

function resetForm() {
   form.reset();
   form.clearErrors();
   submitted.value = false;
}
</script>

<template>
   <div class="flex justify-between">
      <form @submit.prevent="submitForm(false)" class="w-2/3 max-w-2xl mr-8">
         <AppInput
            v-model="form.name"
            label="Nama:"
            placeholder="Masukkan nama pengguna"
            :error="form.errors.name"
            :submitted="submitted"
            autofocus
            required
         />
         <AppInput
            v-model="form.email"
            label="Email:"
            placeholder="Masukkan email pengguna"
            :error="form.errors.email"
            :submitted="submitted"
            type="email"
            required
         />
         <AppSelect
            v-model="selectedCompanies"
            label="Perusahaan:"
            :options="companies.map(company => ({ value: company.id, label: company.name }))"
            placeholder="Pilih perusahaan"
            multiple
         />
         <AppSelect
            v-model="form.branches"
            label="Cabang:"
            :options="filteredBranches.map(branch => ({ value: branch.id, label: `${branch.name} (${branch.branch_group?.company?.name})` }))"
            placeholder="Pilih cabang"
            :error="form.errors.branches"
            :submitted="submitted"
            multiple
            required
         />
         <AppSelect
            v-model="form.roles"
            label="Hak Akses:"
            :options="roles.map(role => ({ value: role.id, label: role.name }))"
            placeholder="Pilih hak akses"
            :error="form.errors.roles"
            :submitted="submitted"
            multiple
            required
         />
         <AppInput
            v-model="form.password"
            label="Password:"
            placeholder="Masukkan password"
            :error="form.errors.password"
            :submitted="submitted"
            type="password"
            :required="!props.user"
         />
         <AppInput
            v-model="form.password_confirmation"
            label="Konfirmasi Password:"
            placeholder="Konfirmasi password"
            :error="form.errors.password_confirmation"
            :submitted="submitted"
            type="password"
            :required="!props.user"
         />
         <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
               {{ props.user ? 'Ubah' : 'Tambah' }} Pengguna
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.user" type="button" @click="submitForm(true)" class="mr-2">
               Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('users.index', filters))">
               Batal
            </AppSecondaryButton>
         </div>
      </form>
      
      <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
         <h3 class="text-lg font-semibold mb-2">Informasi Pengguna</h3>
         <p class="mb-2">Pengguna adalah akun yang dapat mengakses sistem. Pastikan untuk memberikan peran dan cabang yang sesuai.</p>
         <ul class="list-disc list-inside">
            <li>Gunakan email yang valid dan unik</li>
            <li>Password harus minimal 8 karakter</li>
            <li>Pilih peran sesuai dengan tanggung jawab pengguna</li>
            <li>Pilih cabang tempat pengguna akan bekerja</li>
         </ul>
      </div>
   </div>
</template>