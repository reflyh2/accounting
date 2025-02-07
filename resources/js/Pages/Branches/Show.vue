<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
   branch: Object,
   filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteBranch = () => {
    form.delete(route('branches.destroy', props.branch.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
   <Head title="Detail Cabang" />

   <AuthenticatedLayout>
      <template #header>
         <h2>Detail Cabang</h2>
      </template>

      <div>
         <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
               <div class="p-6 text-gray-900">
                  <div class="mb-6">
                     <AppBackLink :href="route('branches.index', filters)" text="Kembali ke Daftar Cabang" />
                  </div>
                  <div class="flex justify-between items-center mb-4">
                     <h3 class="text-lg font-bold">{{ branch.name }}</h3>
                     <div class="flex items-center">
                        <Link :href="route('branches.edit', branch.id)">
                           <AppEditButton title="Edit" />
                        </Link>
                        <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                     </div>
                  </div>
                  <div class="grid grid-cols-2 gap-4 text-sm">
                     <div>
                        <p class="font-semibold">Alamat:</p>
                        <p>{{ branch.address }}</p>
                     </div>
                     <div>
                        <p class="font-semibold">Kelompok Cabang:</p>
                        <p>{{ branch.branch_group.name }}</p>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <DeleteConfirmationModal
         :show="showDeleteConfirmation"
         title="Hapus Cabang"
         @close="showDeleteConfirmation = false"
         @confirm="deleteBranch"
      />
   </AuthenticatedLayout>
</template>