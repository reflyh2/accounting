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
    assetRental: Object,
    filters: Object,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteAssetRental = () => {
    form.delete(route('asset-rentals.destroy', props.assetRental.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
        onError: () => {
             showDeleteConfirmation.value = false;
        }
    });
};

const formattedStatus = computed(() => {
    if (!props.assetRental.status) return '';
    const statusMap = {
        'open': 'Terbuka',
        'paid': 'Lunas',
        'overdue': 'Terlambat',
        'cancelled': 'Dibatalkan',
        'voided': 'Dibatalkan',
        'closed': 'Ditutup',
        'partially_paid': 'Sebagian Lunas'
    };
    return statusMap[props.assetRental.status] || props.assetRental.status;
});

const totalAmount = computed(() => {
    return props.assetRental?.asset_invoice_details?.reduce((sum, detail) => {
        return sum + (Number(detail.quantity) * Number(detail.unit_price));
    }, 0) || 0;
});

</script>

<template>
    <Head title="Detail Faktur Sewa Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Faktur Sewa Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('asset-rentals.index', filters)" text="Kembali ke Daftar Faktur Penyewaan" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ assetRental.number }}</h3>
                            <div class="flex items-center">                              
                              <a :href="route('asset-rentals.print', assetRental.id)" target="_blank">
                                 <AppPrintButton title="Print" />
                              </a>
                              <Link :href="route('asset-rentals.edit', assetRental.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal Faktur:</p>
                                <p>{{ new Date(assetRental.invoice_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Partner:</p>
                                <p>{{ assetRental.partner.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Jatuh Tempo:</p>
                                <p>{{ new Date(assetRental.due_date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>{{ formattedStatus }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ assetRental.branch.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ assetRental.branch.branch_group?.company?.name }}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ assetRental.notes || '-' }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Detail Sewa Aset</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="border border-gray-300 px-1.5 py-1.5">Kode Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5">Nama Aset</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5">Deskripsi</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5">Qty</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5" colspan="2">Harga Satuan</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5">Mulai Sewa</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5">Akhir Sewa</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5" colspan="2">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="detail in assetRental.asset_invoice_details" :key="detail.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset.code }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.asset.name }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ detail.description || '-' }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.quantity) }}</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right">{{ assetRental.currency?.symbol }}</td>
                                        <td class="border border-l-0 border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.unit_price) }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ detail.rental_start_date ? new Date(detail.rental_start_date).toLocaleDateString('id-ID') : '-' }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ detail.rental_end_date ? new Date(detail.rental_end_date).toLocaleDateString('id-ID') : '-' }}</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right align-middle text-sm">{{ assetRental.currency?.symbol }}</td>
                                        <td class="border border-l-0 border-gray-300 px-1.5 py-1.5 text-right">{{ formatNumber(detail.line_amount) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8" class="border border-gray-300 px-1.5 py-1.5 font-semibold text-right">Total</td>
                                        <td class="border border-r-0 border-gray-300 px-1.5 py-1.5 text-right align-middle text-sm">{{ assetRental.currency?.symbol }}</td>
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
            title="Hapus Faktur Sewa Aset"
            @close="showDeleteConfirmation = false"
            @confirm="deleteAssetRental"
        />
    </AuthenticatedLayout>
</template> 