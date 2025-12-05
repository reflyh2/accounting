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
    assetDisposal: Object,
    filters: Object,
    statusOptions: Object,
    disposalTypeOptions: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAssetDisposal = () => {
    form.delete(route('asset-disposals.destroy', props.assetDisposal.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
        onError: () => {
             showDeleteConfirmation.value = false;
        }
    });
};

const formattedStatus = computed(() => {
    return props.statusOptions[props.assetDisposal.status] || props.assetDisposal.status;
});

const formattedDisposalType = computed(() => {
    return props.disposalTypeOptions[props.assetDisposal.disposal_type] || props.assetDisposal.disposal_type;
});

const totalCarryingAmount = computed(() => {
    return props.assetDisposal?.asset_disposal_details?.reduce((sum, detail) => {
        return sum + (Number(detail.carrying_amount));
    }, 0) || 0;
});

const totalProceedsAmount = computed(() => {
    return props.assetDisposal?.asset_disposal_details?.reduce((sum, detail) => {
        return sum + (Number(detail.proceeds_amount));
    }, 0) || 0;
});
</script>

<template>
    <Head title="Detail Pelepasan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pelepasan Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-disposals.index', filters)" text="Kembali ke Daftar Pelepasan Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Dokumen #{{ assetDisposal.number }}</h3>
                            <div class="flex items-center">
                                <a :href="route('asset-disposals.print', assetDisposal.id)" target="_blank" class="mr-1">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link :href="route('asset-disposals.edit', assetDisposal.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ assetDisposal.branch?.branch_group?.company?.name }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Jenis Pelepasan:</p>
                                <p>{{ formattedDisposalType }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ assetDisposal.branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ formattedStatus }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Pelepasan:</p>
                                <p>{{ new Date(assetDisposal.disposal_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Hasil:</p>
                                <p>{{ formatNumber(assetDisposal.proceeds_amount) }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Dibuat Oleh:</p>
                                <p>{{ assetDisposal.creator?.name ?? 'N/A' }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Diubah Oleh:</p>
                                <p>{{ assetDisposal.updater?.name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ assetDisposal.notes || '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Detail Item</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Kode Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Nama Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Deskripsi</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right">Nilai Tercatat</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right">Hasil</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detail in assetDisposal.asset_disposal_details" :key="detail.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.code }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.name }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.notes }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.carrying_amount) }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.proceeds_amount) }}</td>
                                    </tr>
                                     <tr v-if="!assetDisposal.asset_disposal_details || assetDisposal.asset_disposal_details.length === 0">
                                        <td colspan="5" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada detail item.</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <td colspan="3" class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">Total</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">{{ formatNumber(totalCarryingAmount) }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">{{ formatNumber(totalProceedsAmount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Dokumen Pelepasan Aset"
            message="Apakah Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteAssetDisposal"
        />
    </AuthenticatedLayout>
</template> 