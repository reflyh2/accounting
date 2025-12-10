<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    jurisdiction: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteJurisdiction = () => {
    form.delete(route('tax-jurisdictions.destroy', props.jurisdiction.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Yurisdiksi Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Yurisdiksi Pajak</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('tax-jurisdictions.index', filters)" text="Kembali ke Daftar Yurisdiksi" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ jurisdiction.name }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('tax-jurisdictions.edit', jurisdiction.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Kode:</p>
                                <p>{{ jurisdiction.code || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama:</p>
                                <p>{{ jurisdiction.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kode Negara:</p>
                                <p>{{ jurisdiction.country_code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Level:</p>
                                <p>{{ jurisdiction.level }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Otoritas Pajak:</p>
                                <p>{{ jurisdiction.tax_authority || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Yurisdiksi Induk:</p>
                                <p>{{ jurisdiction.parent?.name || '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-6" v-if="jurisdiction.children?.length">
                            <h4 class="text-lg font-semibold mb-2">Yurisdiksi Anak</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Kode</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Nama</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="child in jurisdiction.children" :key="child.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ child.code || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ child.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ child.level }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6" v-if="jurisdiction.components?.length">
                            <h4 class="text-lg font-semibold mb-2">Komponen Pajak</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Kode</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Nama</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Jenis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="component in jurisdiction.components" :key="component.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ component.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ component.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ component.kind }}</td>
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
            title="Hapus Yurisdiksi Pajak"
            @close="showDeleteConfirmation = false"
            @confirm="deleteJurisdiction"
        />
    </AuthenticatedLayout>
</template>
