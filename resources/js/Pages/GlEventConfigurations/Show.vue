<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    configuration: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteConfiguration = () => {
    form.delete(route('gl-event-configurations.destroy', props.configuration.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Konfigurasi GL Event" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Konfigurasi GL Event</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('gl-event-configurations.index', filters)" text="Kembali ke Daftar Konfigurasi GL Event" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ configuration.event_code }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('gl-event-configurations.edit', configuration.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Event Code:</p>
                                <p>{{ configuration.event_code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ configuration.company?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ configuration.branch?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ configuration.is_active ? 'Aktif' : 'Tidak Aktif' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Deskripsi:</p>
                                <p>{{ configuration.description || '-' }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Konfigurasi Entri</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Role</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Direction</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Akun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in configuration.lines" :key="line.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ line.role }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.direction === 'debit' ? 'Debit' : 'Kredit' }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.account.code }} - {{ line.account.name }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Konfigurasi GL Event"
            @close="showDeleteConfirmation = false"
            @confirm="deleteConfiguration"
        />
    </AuthenticatedLayout>
</template>

