<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
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

const deleteSalesDelivery = () => {
    form.delete(route('sales-deliveries.destroy', props.delivery.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Pengiriman Penjualan" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pengiriman Penjualan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6 space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <AppBackLink :href="route('sales-deliveries.index', filters)" text="Kembali ke daftar pengiriman" />
                        <h3 class="text-xl font-semibold mt-2">{{ delivery.delivery_number }}</h3>
                        <p class="text-sm text-gray-500">
                            Tanggal {{ delivery.delivery_date ? new Date(delivery.delivery_date).toLocaleDateString('id-ID') : '-' }}
                        </p>
                    </div>
                    <div class="flex items-center">
                        <DocumentStatusPill
                            :documentKind="DocumentStatusKind.DELIVERY"
                            :status="delivery.status"
                        />
                        
                        <template v-if="canModify">
                            <Link :href="route('sales-deliveries.edit', delivery.id)">
                                <AppEditButton />
                            </Link>
                           <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                        </template>
                        <span v-else class="text-sm text-gray-500 italic">Tidak dapat diubah (sudah diinvoice)</span>
                    </div>
                </div>

                <div class="grid md:grid-cols-3 gap-6 text-sm">
                    <div>
                        <p class="text-gray-500">Sales Orders</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <Link
                                v-for="so in delivery.sales_orders"
                                :key="so.id"
                                :href="route('sales-orders.show', so.id)"
                                class="bg-blue-100 text-blue-600 hover:bg-blue-200 hover:text-blue-800 text-xs px-2 py-1 rounded-full"
                            >
                                {{ so.order_number }}
                            </Link>
                            <span v-if="!delivery.sales_orders?.length" class="font-semibold">-</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-gray-500">Pelanggan</p>
                        <p class="font-semibold">
                            {{ delivery.partner?.name || '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500">Lokasi</p>
                        <p class="font-semibold">
                            {{ delivery.location?.name || '-' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500">Total Qty</p>
                        <p class="font-semibold">{{ formatNumber(delivery.total_quantity) }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Total Amount</p>
                        <p class="font-semibold">
                            {{ delivery.currency?.code }} {{ formatNumber(delivery.total_amount) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500">Total COGS</p>
                        <p class="font-semibold">
                            {{ delivery.currency?.code }} {{ formatNumber(delivery.total_cogs) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-auto border border-gray-200 rounded">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">SKU</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Deskripsi</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">SO #</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Qty</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">COGS/Unit</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Total COGS</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr v-for="line in delivery.lines" :key="line.id">
                                <td class="px-4 py-3 font-semibold">{{ line.sku || '-' }}</td>
                                <td class="px-4 py-3">
                                    <p>{{ line.description }}</p>
                                    <p class="text-xs text-gray-500">UOM: {{ line.uom }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    {{ line.sales_order_line?.sales_order?.order_number || '-' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatNumber(line.quantity) }} {{ line.uom }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatNumber(line.unit_cost_base) }} {{ line.base_uom }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatNumber(line.cogs_total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="delivery.notes" class="bg-gray-50 border border-gray-200 rounded p-4">
                    <p class="text-sm text-gray-500 mb-1">Catatan</p>
                    <p class="text-sm whitespace-pre-line">{{ delivery.notes }}</p>
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
