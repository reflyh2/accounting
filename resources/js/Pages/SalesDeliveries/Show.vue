<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppPrintButton from '@/Components/AppPrintButton.vue';
import DocumentStatusPill from '@/Components/DocumentStatusPill.vue';
import { DocumentStatusKind } from '@/constants/documentStatuses';
import { formatNumber } from '@/utils/numberFormat';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

const props = defineProps({
    delivery: Object,
    canModify: Boolean,
    filters: Object,
});

const showDeleteConfirmation = ref(false);
const form = useForm({});

const deleteSalesDelivery = () => {
    form.delete(route('sales-deliveries.destroy', props.delivery.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Pengiriman Penjualan ${delivery.delivery_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pengiriman Penjualan</p>
                    <h2 class="text-2xl font-semibold">
                        {{ delivery.delivery_number }}
                    </h2>
                </div>
                <DocumentStatusPill
                    :documentKind="DocumentStatusKind.DELIVERY"
                    :status="delivery.status"
                />
            </div>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="space-y-6">
                            <div class="flex justify-between items-center mb-4">
                                <AppBackLink :href="route('sales-deliveries.index', filters)" text="Kembali ke Daftar Pengiriman" />
                                <div class="flex items-center">
                                    <a :href="route('sales-deliveries.print', delivery.id)" target="_blank">
                                        <AppPrintButton title="Print" />
                                    </a>
                                    <template v-if="canModify">
                                        <Link :href="route('sales-deliveries.edit', delivery.id)">
                                            <AppEditButton title="Edit" />
                                        </Link>
                                        <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                                    </template>
                                    <span v-else class="ml-3 text-sm text-gray-500 italic">Tidak dapat diubah (sudah diinvoice)</span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ delivery.delivery_date ? new Date(delivery.delivery_date).toLocaleDateString('id-ID') : '—' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Lokasi:</p>
                                <p>{{ delivery.location?.name || '—' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Pelanggan:</p>
                                <p>{{ delivery.partner?.name || '—' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Qty:</p>
                                <p>{{ formatNumber(delivery.total_quantity, 3) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Amount:</p>
                                <p>{{ delivery.currency?.code }} {{ formatNumber(delivery.total_amount) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total Shipping Charge:</p>
                                <p>{{ delivery.currency?.code }} {{ formatNumber(delivery.actual_shipping_charge) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Total COGS:</p>
                                <p>{{ delivery.currency?.code }} {{ formatNumber(delivery.total_cogs) }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Sales Order:</p>
                                <div v-if="delivery.sales_orders?.length > 0" class="flex flex-wrap gap-1 mt-1">
                                    <Link
                                        v-for="so in delivery.sales_orders"
                                        :key="so.id"
                                        :href="route('sales-orders.show', so.id)"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200"
                                    >
                                        {{ so.order_number }}
                                    </Link>
                                </div>
                                <span v-else>—</span>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Detail Barang</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">SKU</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Deskripsi</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">SO #</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Qty</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">COGS/Unit</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2 text-right">Total COGS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in delivery.lines" :key="line.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2">
                                            <div class="font-medium">{{ line.sku || '-' }}</div>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2">
                                            <p>{{ line.description }}</p>
                                            <p class="text-xs text-gray-500">UOM: {{ line.uom }}</p>
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-sm text-gray-500">
                                            {{ line.sales_order_line?.sales_order?.order_number || '-' }}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">
                                            {{ formatNumber(line.quantity, 3) }} {{ line.uom }}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">
                                            {{ formatNumber(line.unit_cost_base) }}/{{ line.base_uom }}
                                        </td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">
                                            {{ formatNumber(line.cogs_total) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="border border-gray-300 px-4 py-2 font-semibold">Total</td>
                                        <td class="border border-gray-300 px-4 py-2"></td>
                                        <td class="border border-gray-300 px-4 py-2"></td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(delivery.total_quantity, 3) }}</td>
                                        <td class="border border-gray-300 px-4 py-2"></td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(delivery.total_cogs) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div v-if="delivery.notes" class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Catatan</h4>
                            <p class="text-gray-700 whitespace-pre-line">{{ delivery.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            :title="'Hapus Pengiriman Penjualan'"
            :message="'Apakah Anda yakin ingin menghapus pengiriman ini? Stok inventory akan dikembalikan.'"
            @confirm="deleteSalesDelivery"
            @close="showDeleteConfirmation = false"
        />
    </AuthenticatedLayout>
</template>
