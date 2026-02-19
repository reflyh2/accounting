<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
   location: Object,
   filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteLocation = () => {
    form.delete(route('locations.destroy', props.location.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
   <Head title="Detail Lokasi" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Detail Lokasi</h2>
      </template>

      <div>
         <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('locations.index', filters)" text="Kembali ke Daftar Lokasi" />
                  </div>
                  <div class="flex justify-between items-center mb-4">
                     <h3 class="text-lg font-bold">{{ location.name }}</h3>
                     <div class="flex items-center">
                        <Link :href="route('locations.edit', location.id)">
                           <AppEditButton title="Edit" />
                        </Link>
                        <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                     </div>
                  </div>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                     <div>
                        <p class="font-semibold">Kode:</p>
                        <p>{{ location.code }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Tipe:</p>
                        <p>{{ location.type }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Cabang:</p>
                        <p>{{ location.branch.name }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Status:</p>
                        <p>{{ location.is_active ? 'Aktif' : 'Tidak Aktif' }}</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <DeleteConfirmationModal
         :show="showDeleteConfirmation"
         title="Hapus Lokasi"
         @close="showDeleteConfirmation = false"
         @confirm="deleteLocation"
      />
   </AuthenticatedLayout>
</template>
