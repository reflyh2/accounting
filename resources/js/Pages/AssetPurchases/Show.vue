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
    assetPurchase: Object, // Renamed from journal
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAssetPurchase = () => {
    form.delete(route('asset-purchases.destroy', props.assetPurchase.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
            // Redirect maybe handled by controller depending on preserveState
        },
        onError: () => {
             showDeleteConfirmation.value = false;
             // Handle error feedback
        }
    });
};

const formattedStatus = computed(() => {
    if (!props.assetPurchase.status) return '';
    return props.assetPurchase.status
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
});

const totalAmount = computed(() => {
    return props.assetPurchase?.asset_invoice_details?.reduce((sum, detail) => {
        return sum + (Number(detail.quantity) * Number(detail.unit_price));
    }, 0) || 0;
});

</script>

<template>
    <Head title="Detail Faktur Pembelian Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Faktur Pembelian Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-purchases.index', filters)" text="Kembali ke Daftar Faktur Pembelian Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Faktur #{{ assetPurchase.number }}</h3>
                            <div class="flex items-center">
                                <a :href="route('asset-purchases.print', assetPurchase.id)" target="_blank" class="mr-1">
                                    <AppPrintButton title="Print" />
                                </a>
                                <Link :href="route('asset-purchases.edit', assetPurchase.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>

                        <!-- Invoice Header Info -->
                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ assetPurchase.branch?.branch_group?.company?.name }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Partner (Vendor):</p>
                                <p>{{ assetPurchase.partner?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ assetPurchase.branch?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ formattedStatus }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Faktur:</p>
                                <p>{{ new Date(assetPurchase.invoice_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Tanggal Jatuh Tempo:</p>
                                <p>{{ new Date(assetPurchase.due_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Dibuat Oleh:</p>
                                <p>{{ assetPurchase.creator?.name ?? 'N/A' }}</p>
                            </div>
                             <div>
                                <p class="font-semibold">Diubah Oleh:</p>
                                <p>{{ assetPurchase.updater?.name ?? 'N/A' }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ assetPurchase.notes || '-' }}</p>
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
                                    <tr v-for="detail in assetPurchase.asset_invoice_details" :key="detail.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.code }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset?.name }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.description }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.quantity, 0) }}</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right">{{ assetPurchase.currency?.symbol }}</td>
                                        <td class="border border-l-0 border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.unit_price) }}</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right">{{ assetPurchase.currency?.symbol }}</td>
                                        <td class="border border-l-0 border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.line_amount) }}</td>
                                    </tr>
                                     <tr v-if="!assetPurchase.asset_invoice_details || assetPurchase.asset_invoice_details.length === 0">
                                        <td colspan="6" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada detail item.</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <td colspan="6" class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">Total Faktur</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right font-semibold">{{ assetPurchase.currency?.symbol }}</td>
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
            title="Hapus Faktur Pembelian Aset"
            message="Apakah Anda yakin ingin menghapus faktur pembelian aset ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteAssetPurchase"
        />
    </AuthenticatedLayout>
</template> 