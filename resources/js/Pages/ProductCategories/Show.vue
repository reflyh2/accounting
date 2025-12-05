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

function deleteCategory() {
    form.delete(route('catalog.product-categories.destroy', props.category.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head title="Detail Kategori Produk" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Kategori Produk</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('catalog.product-categories.index', filters)" text="Kembali ke Kategori Produk" />
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-bold">{{ category.code }} - {{ category.name }}</h3>
                            <p class="text-sm text-gray-500">{{ category.path || '-' }}</p>
                        </div>
                        <div class="flex items-center">
                            <Link :href="route('catalog.product-categories.edit', category.id)">
                                <AppEditButton title="Ubah" />
                            </Link>
                            <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="font-semibold">Perusahaan</p>
                            <p>{{ category.company?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Set Atribut</p>
                            <p>{{ category.attribute_set?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Kategori Induk</p>
                            <p>{{ category.parent?.name || '-' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold">Urutan</p>
                            <p>{{ category.sort_order ?? '-' }}</p>
                        </div>
                    </div>

                    <div v-if="category.children?.length" class="mt-8">
                        <h4 class="font-semibold mb-2">Sub Kategori</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm border border-gray-200 rounded">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-left px-3 py-2 border-b border-gray-200">Kode</th>
                                        <th class="text-left px-3 py-2 border-b border-gray-200">Nama</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="child in category.children" :key="child.id" class="border-b border-gray-200">
                                        <td class="px-3 py-2">{{ child.code }}</td>
                                        <td class="px-3 py-2">{{ child.name }}</td>
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
            title="Hapus Kategori"
            @close="showDeleteConfirmation = false"
            @confirm="deleteCategory"
        />
    </AuthenticatedLayout>
</template>

