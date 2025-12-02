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

function deleteTransaction() {
    deleteForm.delete(route('inventory.transfers.destroy', props.transaction.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Transfer ${transaction.transaction_number}`" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Transfer Persediaan</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <AppBackLink :href="route('inventory.transfers.index')" text="Kembali ke daftar transfer" />
                        <div class="flex items-center gap-2">
                            <AppEditButton :href="route('inventory.transfers.edit', transaction.id)" />
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
                            <p class="text-gray-500">Lokasi Asal</p>
                            <p class="font-semibold">
                                {{ transaction.location_from ? `${transaction.location_from.code} — ${transaction.location_from.name}` : '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Lokasi Tujuan</p>
                            <p class="font-semibold">
                                {{ transaction.location_to ? `${transaction.location_to.code} — ${transaction.location_to.name}` : '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500">Total Qty</p>
                            <p class="font-semibold">
                                {{ formatQty(transaction.totals.quantity) }}
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
            title="Hapus Transfer"
            message="Apakah Anda yakin ingin menghapus transaksi ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deleteTransaction"
        />
    </AuthenticatedLayout>
</template>

