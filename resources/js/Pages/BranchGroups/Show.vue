<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    branchGroup: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteBranchGroup = () => {
    form.delete(route('branch-groups.destroy', props.branchGroup.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Kelompok Cabang" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Kelompok Cabang</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('branch-groups.index', filters)" text="Kembali ke Daftar Kelompok Cabang" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ branchGroup.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('branch-groups.edit', branchGroup.id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="mb-4">
                            <p class="font-semibold text-sm">Perusahaan:</p>
                            <p class="text-sm">{{ branchGroup.company.name }}</p>
                        </div>
                        <div>
                            <p class="font-semibold text-sm">Jumlah Cabang:</p>
                            <p class="text-sm">{{ branchGroup.branches.length }}</p>
                        </div>
                        <div class="mt-4">
                            <h4 class="font-semibold text-sm">Daftar Cabang:</h4>
                            <ul class="list-disc list-inside text-sm">
                                <li v-for="branch in branchGroup.branches" :key="branch.id" class="py-1">
                                    {{ branch.name }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Kelompok Cabang"
            @close="showDeleteConfirmation = false"
            @confirm="deleteBranchGroup"
        />
    </AuthenticatedLayout>
</template>