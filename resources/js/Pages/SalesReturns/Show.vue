<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    salesReturn: Object,
    filters: Object,
    reasonOptions: Array,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteSalesReturn = () => {
    form.delete(route('sales-returns.destroy', props.salesReturn.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
    });
};
</script>

<template>
    <Head title="Detail Retur Penjualan" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Retur Penjualan</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('sales-returns.index', filters)" text="Kembali ke Daftar Retur Penjualan" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ salesReturn.return_number }}</h3>
                            <div class="flex items-center">
                              <Link :href="route('sales-returns.edit', salesReturn.id)">
                                 <AppEditButton title="Edit" />
                              </Link>
                              <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal:</p>
                                <p>{{ salesReturn.return_date }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ salesReturn.location.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Customer:</p>
                                <p>{{ salesReturn.partner.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Alasan:</p>
                                <p>{{ salesReturn.reason_label }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ salesReturn.notes }}</p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-2">Entri Retur</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">No. Akun</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Nama Akun</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Qty</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Satuan</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Harga</th>
                                        <th class="bg-gray-100 border border-gray-300 px-4 py-2">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="line in salesReturn.lines" :key="line.id" class="group">
                                        <td class="border border-gray-300 px-4 py-2">{{ line.variant.sku }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.variant.product_name }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.quantity) }}</td>
                                        <td class="border border-gray-300 px-4 py-2">{{ line.uom.code }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.unit_price) }}</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right">{{ formatNumber(line.line_total) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="border border-gray-300 px-4 py-2 font-semibold text-right">Total</td>
                                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">{{ formatNumber(salesReturn.lines.reduce((total, line) => total + Number(line.line_total), 0)) }}</td>
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
            title="Hapus Retur Penjualan"
            @close="showDeleteConfirmation = false"
            @confirm="deleteSalesReturn"
        />
    </AuthenticatedLayout>
</template>
