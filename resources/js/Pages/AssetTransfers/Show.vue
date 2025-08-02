<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppApproveButton from '@/Components/AppApproveButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppRejectButton from '@/Components/AppRejectButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    assetTransfer: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAssetTransfer = () => {
    form.delete(route('asset-transfers.destroy', props.assetTransfer.id), {
        onSuccess: () => { showDeleteConfirmation.value = false; }
    });
};

const approveTransfer = () => {
    router.put(route('asset-transfers.approve', props.assetTransfer.id), {}, {
        preserveScroll: true,
    });
};

const rejectTransfer = () => {
     router.put(route('asset-transfers.reject', props.assetTransfer.id), {}, {
        preserveScroll: true,
    });
};

const cancelTransfer = () => {
     router.put(route('asset-transfers.cancel', props.assetTransfer.id), {}, {
        preserveScroll: true,
    });
};


const formattedStatus = computed(() => {
    if (!props.assetTransfer.status) return '';
    return props.assetTransfer.status
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
});

</script>

<template>
    <Head title="Detail Transfer Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Transfer Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-transfers.index', filters)" text="Kembali ke Daftar Transfer Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Transfer #{{ assetTransfer.number }}</h3>
                            <div class="flex items-center">
                                <a :href="route('asset-transfers.print', assetTransfer.id)" target="_blank" class="mr-1">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link v-if="assetTransfer.status === 'draft'" :href="route('asset-transfers.edit', assetTransfer.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton v-if="assetTransfer.status === 'draft'" @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <div v-if="assetTransfer.status === 'draft'" class="flex items-center space-x-2 mb-4 p-4 bg-slate-50 border border-slate-200 rounded-lg">
                            <p class="text-sm text-slate-800 font-medium">Tindakan yang dapat dilakukan:</p>
                            <AppApproveButton @click="approveTransfer">Setujui</AppApproveButton>
                            <AppRejectButton @click="rejectTransfer">Tolak</AppRejectButton>
                            <AppSecondaryButton @click="cancelTransfer">Batalkan</AppSecondaryButton>
                        </div>

                        <!-- Transfer Header Info -->
                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                             <div>
                                <p class="font-semibold">Tanggal Transfer:</p>
                                <p>{{ new Date(assetTransfer.transfer_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ formattedStatus }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Dari Perusahaan:</p>
                                <p>{{ assetTransfer.from_company?.name }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Ke Perusahaan:</p>
                                <p>{{ assetTransfer.to_company?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Dari Cabang:</p>
                                <p>{{ assetTransfer.from_branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Ke Cabang:</p>
                                <p>{{ assetTransfer.to_branch?.name }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Dibuat Oleh:</p>
                                <p>{{ assetTransfer.creator?.name ?? 'N/A' }} pada {{ new Date(assetTransfer.created_at).toLocaleString('id-ID') }}</p>
                            </div>
                            <div v-if="assetTransfer.status === 'approved'">
                                <p class="font-semibold">Disetujui Oleh:</p>
                                <p>{{ assetTransfer.approver?.name ?? 'N/A' }} pada {{ new Date(assetTransfer.approved_at).toLocaleString('id-ID') }}</p>
                            </div>
                            <div v-if="assetTransfer.status === 'rejected'">
                                <p class="font-semibold">Ditolak Oleh:</p>
                                <p>{{ assetTransfer.rejector?.name ?? 'N/A' }} pada {{ new Date(assetTransfer.rejected_at).toLocaleString('id-ID') }}</p>
                            </div>
                             <div v-if="assetTransfer.status === 'cancelled'">
                                <p class="font-semibold">Dibatalkan Oleh:</p>
                                <p>{{ assetTransfer.canceller?.name ?? 'N/A' }} pada {{ new Date(assetTransfer.cancelled_at).toLocaleString('id-ID') }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ assetTransfer.notes || '-' }}</p>
                            </div>
                        </div>

                        <!-- Transfer Details Table -->
                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Detail Item Transfer</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Kode Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Nama Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detail in assetTransfer.asset_transfer_details" :key="detail.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.code }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.name }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.notes }}</td>
                                    </tr>
                                     <tr v-if="!assetTransfer.asset_transfer_details || assetTransfer.asset_transfer_details.length === 0">
                                        <td colspan="3" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada detail item.</td>
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
            title="Hapus Transfer Aset"
            message="Apakah Anda yakin ingin menghapus transfer aset ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteAssetTransfer"
        />
    </AuthenticatedLayout>
</template> 