<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    category: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteCategory = () => {
    form.delete(route('tax-categories.destroy', props.category.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Kategori Pajak" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Kategori Pajak</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('tax-categories.index', filters)" text="Kembali ke Daftar Kategori" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ category.name }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('tax-categories.edit', category.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Kode:</p>
                                <p>{{ category.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama:</p>
                                <p>{{ category.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ category.company?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Berlaku Untuk:</p>
                                <p>{{ category.applies_to }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Perilaku Default:</p>
                                <p>{{ category.default_behavior }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Deskripsi:</p>
                                <p>{{ category.description || '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-6" v-if="category.tax_rules?.length">
                            <h4 class="text-lg font-semibold mb-2">Aturan Pajak</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Komponen</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Yurisdiksi</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Tarif</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="rule in category.tax_rules" :key="rule.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ rule.component?.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ rule.jurisdiction?.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ rule.rate_type === 'percent' ? rule.rate_value + '%' : rule.rate_value }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6" v-if="category.products?.length">
                            <h4 class="text-lg font-semibold mb-2">Produk Terkait</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Kode</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Nama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="product in category.products.slice(0, 10)" :key="product.id">
                                        <td class="border border-gray-300 px-4 py-2">{{ product.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ product.name }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-if="category.products.length > 10" class="text-sm text-gray-500 mt-2">
                                Dan {{ category.products.length - 10 }} produk lainnya...
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Kategori Pajak"
            @close="showDeleteConfirmation = false"
            @confirm="deleteCategory"
        />
    </AuthenticatedLayout>
</template>
