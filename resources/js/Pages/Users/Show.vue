<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    user: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteUser = () => {
    form.delete(route('users.destroy', props.user.global_id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pengguna</h2>
        </template>

        <div>
            <div class="min-w-min md:min-w-max mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('users.index', filters)" text="Kembali ke Daftar Pengguna" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ user.name }}</h3>
                            <div class="flex items-center">
                                <Link :href="route('users.edit', user.global_id)">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="font-semibold">Email:</p>
                                <p>{{ user.email }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Peran:</p>
                                <p>{{ user.roles.map(role => role.name).join(', ') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <ul>
                                    <li v-for="branch in user.branches" :key="branch.id">
                                        {{ branch.name }} ({{ branch.branch_group.company.name }})
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Pengguna"
            @close="showDeleteConfirmation = false"
            @confirm="deleteUser"
        />
    </AuthenticatedLayout>
</template>