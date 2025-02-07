<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { LockClosedIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
   role: Object,
   filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteRole = () => {
   form.delete(route('roles.destroy', props.role.id), {
      onSuccess: () => {
         showDeleteConfirmation.value = false;
      },
   });
};
</script>

<template>
   <Head title="Detail Hak Akses" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Detail Hak Akses</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('roles.index', filters)" text="Kembali ke Daftar Hak Akses" />
                  </div>
                  <div class="flex justify-between items-center mb-6">
                     <h3 class="text-lg font-bold">{{ role.name }}</h3>
                     <div class="flex items-center">
                        <Link :href="route('roles.permissions', role.id)" class="ml-2">
                           <button type="button" title="Set Hak Akses" class="inline-flex items-center justify-center align-middle w-5 h-5 md:ml-2 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50">
                              <LockClosedIcon class="h-4 w-4" />
                           </button>
                        </Link>
                        <Link :href="route('roles.edit', role.id)">
                           <AppEditButton title="Edit" />
                        </Link>
                        <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                     </div>
                  </div>
                  <div class="mt-4">
                     <h4 class="font-semibold">Deskripsi:</h4>
                     <p>{{ role.description }}</p>
                  </div>
                  <div class="mt-4">
                     <h4 class="font-semibold">Tingkat Akses:</h4>
                     <p>{{ {
                        'own': 'Data Sendiri',
                        'branch': 'Cabang',
                        'branch_group': 'Kelompok Cabang',
                        'company': 'Perusahaan'
                     }[role.access_level] }}</p>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Hak Akses"
            @close="showDeleteConfirmation = false"
            @confirm="deleteRole"
        />
   </AuthenticatedLayout>
</template>