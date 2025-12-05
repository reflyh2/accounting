<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: Object,
    filters: Object,
    assetTypes: Object,
    acquisitionTypes: Object,
    depreciationMethods: Object,
    statusOptions: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAsset = () => {
    form.delete(route('assets.destroy', props.asset.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('assets.index', filters)" text="Kembali ke Daftar Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ asset.code }}: {{ asset.name }}</h3>
                            <div class="flex items-center">                              
                              <a :href="route('assets.print', asset.id)" target="_blank">
                                 <AppPrintButton title="Print" />
                              </a>
                              <Link :href="route('assets.edit', asset.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Kode:</p>
                                <p>{{ asset.code }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nama:</p>
                                <p>{{ asset.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kategori:</p>
                                <p>{{ asset.category.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jenis:</p>
                                <p>{{ assetTypes[asset.type] }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cara Perolehan:</p>
                                <p>{{ acquisitionTypes[asset.acquisition_type] }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Perolehan:</p>
                                <p>{{ asset.acquisition_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nilai Perolehan:</p>
                                <p>{{ formatNumber(asset.cost_basis) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nilai Residu:</p>
                                <p>{{ formatNumber(asset.salvage_value) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Dapat Disusutkan:</p>
                                <p>{{ asset.is_depreciable ? 'Ya' : 'Tidak' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Dapat Diamortisasi:</p>
                                <p>{{ asset.is_amortizable ? 'Ya' : 'Tidak' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Metode Penyusutan:</p>
                                <p>{{ depreciationMethods[asset.depreciation_method] }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Umur Ekonomis (Bulan):</p>
                                <p>{{ asset.useful_life_months }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Mulai Penyusutan:</p>
                                <p>{{ asset.depreciation_start_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Akumulasi Penyusutan:</p>
                                <p>{{ formatNumber(asset.accumulated_depreciation) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Nilai Buku:</p>
                                <p>{{ formatNumber(asset.net_book_value) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ statusOptions[asset.status] }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kadaluwarsa Garansi:</p>
                                <p>{{ asset.warranty_expiry || '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ asset.company.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ asset.branch.name }}</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="font-semibold">Catatan:</p>
                            <p class="whitespace-pre-wrap">{{ asset.notes || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Aset"
            @close="showDeleteConfirmation = false"
            @confirm="deleteAsset"
        />
    </AuthenticatedLayout>
</template> 