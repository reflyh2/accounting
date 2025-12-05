<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

const props = defineProps({
    transaction: Object,
});

const deleteForm = useForm({});
const showDeleteConfirmation = ref(false);

const formatQty = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 3 });
const formatValue = (value) => Number(value ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function deleteTransaction() {
    deleteForm.delete(route('inventory.receipts.destroy', props.transaction.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Penerimaan ${transaction.transaction_number}`" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Penerimaan</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <AppBackLink :href="route('inventory.receipts.index')" text="Kembali ke daftar penerimaan" />
                        <div class="flex items-center gap-2">
                            <AppEditButton :href="route('inventory.receipts.edit', transaction.id)" />
                            <AppDeleteButton @click="showDeleteConfirmation = true" />
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Nomor Transaksi</p>
                            <p class="font-semibold">{{ transaction.transaction_number }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Tanggal</p>
                            <p class="font-semibold">{{ new Date(transaction.transaction_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Lokasi Tujuan</p>
                            <p class="font-semibold">
                                {{ transaction.location_to ? `${transaction.location_to.code} — ${transaction.location_to.name}` : '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Referensi</p>
                            <p class="font-semibold">
                                {{ transaction.source_type || '-' }}
                                <span v-if="transaction.source_id" class="text-gray-500">
                                    (#{{ transaction.source_id }})
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Total Qty</p>
                            <p class="font-semibold">
                                {{ formatQty(transaction.totals.quantity) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Nilai Persediaan</p>
                            <p class="font-semibold">
                                {{ formatValue(transaction.totals.value) }}
                            </p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-gray-500">Catatan</p>
                            <p class="font-semibold whitespace-pre-line">
                                {{ transaction.notes || '-' }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-md font-semibold mb-2">Detail Barang</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 border border-gray-200 text-left">SKU</th>
                                        <th class="px-3 py-2 border border-gray-200 text-left">Produk</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Qty</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Harga Satuan</th>
                                        <th class="px-3 py-2 border border-gray-200 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in transaction.lines" :key="line.id">
                                        <td class="px-3 py-2 border border-gray-200">{{ line.product_variant?.sku }}</td>
                                        <td class="px-3 py-2 border border-gray-200">{{ line.product_variant?.product_name }}</td>
                                        <td class="px-3 py-2 border border-gray-200 text-right">
                                            {{ formatQty(line.quantity) }}
                                            <span class="text-gray-500 text-xs ml-1">{{ line.uom_label }}</span>
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right">
                                            {{ line.unit_cost !== null ? formatValue(line.unit_cost) : '-' }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-right">
                                            {{ line.subtotal !== null ? formatValue(line.subtotal) : '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td colspan="2" class="px-3 py-2 border border-gray-200 text-right">Total</td>
                                        <td class="px-3 py-2 border border-gray-200 text-right">
                                            {{ formatQty(transaction.totals.quantity) }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200"></td>
                                        <td class="px-3 py-2 border border-gray-200 text-right">
                                            {{ formatValue(transaction.totals.value) }}
                                        </td>
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
            title="Hapus Penerimaan"
            message="Apakah Anda yakin ingin menghapus transaksi ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteTransaction"
        />
    </AuthenticatedLayout>
</template>

