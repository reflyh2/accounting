<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppViewButton from '@/Components/AppViewButton.vue';
import { ref } from 'vue';
import { statusOptions, getStatusClass } from '@/constants/assetStatus';

const props = defineProps({
    category: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteCategory = () => {
    form.delete(route('asset-categories.destroy', props.category.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Kategori Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Kategori Aset</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-categories.index', filters)" text="Kembali ke Daftar Kategori" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ category.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('asset-categories.edit', category.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div class="col-span-2">
                                <p class="font-semibold">Deskripsi:</p>
                                <p>{{ category.description || '-' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Perusahaan:</p>
                                <div class="flex flex-wrap gap-2 mt-1">
                                    <span 
                                        v-for="company in category.companies" 
                                        :key="company.id"
                                        class="px-2 py-1 bg-main-100 text-main-800 rounded-full text-xs"
                                    >
                                        {{ company.name }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-lg font-semibold">Daftar Aset</h4>
                            </div>
                            <table v-if="category.assets?.length" class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-100">
                                       <th class="border border-gray-300 px-4 py-2">#</th>
                                       <th class="border border-gray-300 px-4 py-2">Nama Aset</th>
                                       <th class="border border-gray-300 px-4 py-2">Perusahaan</th>
                                       <th class="border border-gray-300 px-4 py-2">Cabang</th>
                                       <th class="border border-gray-300 px-4 py-2">Status</th>
                                       <th class="border border-gray-300 px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(asset, index) in category.assets" :key="asset.id">
                                       <td class="border border-gray-300 px-4 py-2">{{ index + 1 }}</td>
                                       <td class="border border-gray-300 px-4 py-2">{{ asset.name }}</td>
                                       <td class="border border-gray-300 px-4 py-2">{{ asset.branch.branch_group.company.name }}</td>
                                       <td class="border border-gray-300 px-4 py-2">{{ asset.branch.name }}</td>
                                       <td class="border border-gray-300 px-4 py-2 text-center">
                                          <span :class="getStatusClass(asset.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                             {{ statusOptions.find(option => option.value === asset.status)?.label }}
                                          </span>
                                       </td>
                                       <td class="border border-gray-300 px-4 py-2 text-center">
                                          <Link 
                                             :href="route('assets.show', asset.id)"
                                             class="text-main-600 hover:text-main-900"
                                          >
                                             <AppViewButton title="Detail" />
                                          </Link>
                                       </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="text-gray-500 italic">Belum ada aset dalam kategori ini</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Kategori"
            @close="showDeleteConfirmation = false"
            @confirm="deleteCategory"
        >
            <template #message>
                Apakah Anda yakin ingin menghapus kategori ini? Kategori yang memiliki aset tidak dapat dihapus.
            </template>
        </DeleteConfirmationModal>
    </AuthenticatedLayout>
</template> 