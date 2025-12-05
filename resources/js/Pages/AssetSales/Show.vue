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
    assetSale: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAssetSale = () => {
    form.delete(route('asset-sales.destroy', props.assetSale.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
        onError: () => {
             showDeleteConfirmation.value = false;
        }
    });
};

const formattedStatus = computed(() => {
    const statusLabels = {
        'open': 'Belum Dibayar',
        'partially_paid': 'Dibayar Sebagian',
        'paid': 'Lunas'
    };
    return statusLabels[props.assetSale.status] || props.assetSale.status;
});

const totalAmount = computed(() => {
    return props.assetSale?.asset_invoice_details?.reduce((sum, detail) => {
        return sum + (Number(detail.quantity) * Number(detail.unit_price));
    }, 0) || 0;
});

</script>

<template>
    <Head title="Detail Faktur Penjualan Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Faktur Penjualan Aset</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-sales.index', filters)" text="Kembali ke Daftar Faktur Penjualan Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Faktur #{{ assetSale.number }}</h3>
                            <div class="flex items-center">
                                <a :href="route('asset-sales.print', assetSale.id)" target="_blank" class="mr-1">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link :href="route('asset-sales.edit', assetSale.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <!-- Invoice Header Info -->
                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ assetSale.branch?.branch_group?.company?.name }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Customer:</p>
                                <p>{{ assetSale.partner?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ assetSale.branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ formattedStatus }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Faktur:</p>
                                <p>{{ new Date(assetSale.invoice_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Jatuh Tempo:</p>
                                <p>{{ new Date(assetSale.due_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Dibuat Oleh:</p>
                                <p>{{ assetSale.creator?.name ?? 'N/A' }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Diubah Oleh:</p>
                                <p>{{ assetSale.updater?.name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ assetSale.notes || '-' }}</p>
                            </div>
                        </div>

                        <!-- Invoice Details Table -->
                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Detail Item Faktur</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Kode Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Nama Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Deskripsi</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right">Qty</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right" colspan="2">Harga Satuan</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right" colspan="2">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detail in assetSale.asset_invoice_details" :key="detail.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.code }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.name }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.description }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.quantity, 0) }}</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right">{{ assetSale.currency?.symbol }}</td>
                                        <td class="border border-l-0 border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.unit_price) }}</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right">{{ assetSale.currency?.symbol }}</td>
                                        <td class="border border-l-0 border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.line_amount) }}</td>
                                    </tr>
                                     <tr v-if="!assetSale.asset_invoice_details || assetSale.asset_invoice_details.length === 0">
                                        <td colspan="8" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada detail item.</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <td colspan="6" class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">Total Faktur</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right font-semibold">{{ assetSale.currency?.symbol }}</td>
                                        <td class="border border-l-0 border-gray-300 px-1.5 py-1.5 text-right font-semibold">{{ formatNumber(totalAmount) }}</td>
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
            title="Hapus Faktur Penjualan Aset"
            message="Apakah Anda yakin ingin menghapus faktur penjualan aset ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteAssetSale"
        />
    </AuthenticatedLayout>
</template> 