<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    component: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteComponent = () => {
    form.delete(route('tax-components.destroy', props.component.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Komponen Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Komponen Pajak</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('tax-components.index', filters)" text="Kembali ke Daftar Komponen" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ component.name }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('tax-components.edit', component.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Kode:</p>
                                <p>{{ component.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama:</p>
                                <p>{{ component.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Yurisdiksi:</p>
                                <p>{{ component.jurisdiction?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jenis:</p>
                                <p>{{ component.kind }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mode Kaskade:</p>
                                <p>{{ component.cascade_mode }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Mode Pengkreditan:</p>
                                <p>{{ component.deductible_mode }}</p>
                            </div>
                        </div>

                        <div class="mt-6" v-if="component.tax_rules?.length">
                            <h4 class="text-lg font-semibold mb-2">Aturan Pajak Terkait</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Kategori</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Tarif</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Berlaku Dari</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rule in component.tax_rules" :key="rule.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ rule.tax_category?.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ rule.rate_type === 'percent' ? rule.rate_value + '%' : rule.rate_value }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ rule.effective_from }}</td>
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
            title="Hapus Komponen Pajak"
            @close="showDeleteConfirmation = false"
            @confirm="deleteComponent"
        />
    </AuthenticatedLayout>
</template>
