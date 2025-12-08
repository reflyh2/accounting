<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    bom: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteBom = () => {
    form.delete(route('bill-of-materials.destroy', props.bom.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};

const statusLabels = {
    'draft': 'Draft',
    'active': 'Aktif',
    'inactive': 'Tidak Aktif'
};
</script>

<template>
    <Head title="Detail Bill of Materials" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Bill of Materials</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('bill-of-materials.index', filters)" text="Kembali ke Daftar BOM" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ bom.bom_number }} - {{ bom.name }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('bill-of-materials.edit', bom.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Nama BOM:</p>
                                <p>{{ bom.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Versi:</p>
                                <p>{{ bom.version }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ statusLabels[bom.status] || bom.status }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Produk Jadi:</p>
                                <p>{{ bom.finished_product.name }}</p>
                                <p v-if="bom.finished_product_variant" class="text-sm text-gray-600 mt-1">
                                    Varian: {{ bom.finished_product_variant.name }} ({{ bom.finished_product_variant.sku }})
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Kuantitas Jadi:</p>
                                <p>{{ formatNumber(bom.finished_quantity) }} {{ bom.finished_uom.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Efektif:</p>
                                <p>{{ bom.effective_date ? new Date(bom.effective_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ bom.company.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jumlah Komponen:</p>
                                <p>{{ bom.bom_lines.length }}</p>
                            </div>
                        </div>
                        <div v-if="bom.description" class="mb-6">
                            <p class="font-semibold">Deskripsi:</p>
                            <p>{{ bom.description }}</p>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Komponen BOM</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">No.</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Komponen</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Varian</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Kuantitas per Unit</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Satuan</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Scrap (%)</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Backflush</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Operasi</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in bom.bom_lines" :key="line.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2 text-center">{{ line.line_number }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.component_product.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            <span v-if="line.component_product_variant">{{ line.component_product_variant.name }} ({{ line.component_product_variant.sku }})</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity_per) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-center">{{ line.uom.name }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.scrap_percentage) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-center">
                                            <span v-if="line.backflush" class="text-green-600">✓</span>
                                            <span v-else class="text-gray-400">✗</span>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.operation || '-' }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.notes || '-' }}</td>
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
            title="Hapus Bill of Materials"
            @close="showDeleteConfirmation = false"
            @confirm="deleteBom"
        />
    </AuthenticatedLayout>
</template>
