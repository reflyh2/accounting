<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    costPool: Object,
    filters: Object,
});

const showDeleteConfirmation = ref(false);

const poolTypeLabels = {
    'asset': 'Aset',
    'service': 'Layanan',
    'branch': 'Cabang'
};

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID');
}

function deletePool() {
    router.delete(route('costing.cost-pools.destroy', props.costPool.id), {
        onFinish: () => {
            showDeleteConfirmation.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Pool Biaya: ${costPool.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pool Biaya</p>
                    <h2 class="text-2xl font-semibold">
                        {{ costPool.code }} - {{ costPool.name }}
                    </h2>
                </div>
                <span 
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                    :class="costPool.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                >
                    {{ costPool.is_active ? 'Aktif' : 'Non-aktif' }}
                </span>
            </div>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <AppBackLink :href="route('costing.cost-pools.index', filters)" text="Kembali ke Daftar Pool Biaya" />
                            <div class="flex flex-wrap items-center">
                                <Link :href="route('costing.cost-pools.edit', costPool.id)" class="ml-3">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Delete" class="ml-3" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Informasi Pool</h3>
                                <p><span class="text-gray-500 text-sm">Kode:</span> {{ costPool.code }}</p>
                                <p><span class="text-gray-500 text-sm">Nama:</span> {{ costPool.name }}</p>
                                <p><span class="text-gray-500 text-sm">Tipe:</span> {{ poolTypeLabels[costPool.pool_type] || costPool.pool_type }}</p>
                                <p><span class="text-gray-500 text-sm">Perusahaan:</span> {{ costPool.company?.name || '—' }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Detail</h3>
                                <p v-if="costPool.asset"><span class="text-gray-500 text-sm">Aset:</span> {{ costPool.asset?.name }}</p>
                                <p v-if="costPool.branch"><span class="text-gray-500 text-sm">Cabang:</span> {{ costPool.branch?.name }}</p>
                                <p v-if="costPool.allocation_rule"><span class="text-gray-500 text-sm">Aturan Alokasi:</span> {{ costPool.allocation_rule }}</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded p-4 space-y-2">
                                <h3 class="text-sm font-semibold text-gray-600">Ringkasan Jumlah</h3>
                                <p class="flex justify-between text-sm">
                                    <span>Total Akumulasi</span>
                                    <span class="font-medium text-lg">{{ formatNumber(costPool.accumulated_amount) }}</span>
                                </p>
                                <p class="flex justify-between text-sm">
                                    <span>Total Teralokasi</span>
                                    <span class="text-green-600">{{ formatNumber(costPool.allocated_amount) }}</span>
                                </p>
                                <p class="flex justify-between text-sm border-t pt-2">
                                    <span>Sisa Belum Teralokasi</span>
                                    <span :class="(costPool.accumulated_amount - costPool.allocated_amount) > 0 ? 'text-orange-600 font-medium' : 'text-green-600'">
                                        {{ formatNumber(costPool.accumulated_amount - costPool.allocated_amount) }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div v-if="costPool.notes" class="bg-white border border-gray-200 rounded p-4">
                            <h3 class="text-sm font-semibold text-gray-600 mb-2">Catatan</h3>
                            <p class="text-gray-700 whitespace-pre-line">{{ costPool.notes }}</p>
                        </div>

                        <div v-if="costPool.cost_entries?.length" class="bg-white border border-gray-200 rounded">
                            <h3 class="text-sm font-semibold text-gray-600 p-4 border-b">Catatan Biaya Terakhir</h3>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">#</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Tanggal</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Produk</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(entry, index) in costPool.cost_entries" :key="entry.id">
                                        <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
                                        <td class="px-4 py-3">{{ formatDate(entry.cost_date) }}</td>
                                        <td class="px-4 py-3">{{ entry.product?.name || '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(entry.amount_base) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="costPool.allocations?.length" class="bg-white border border-gray-200 rounded">
                            <h3 class="text-sm font-semibold text-gray-600 p-4 border-b">Alokasi Terakhir</h3>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">#</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Periode</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600">Faktur</th>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(allocation, index) in costPool.allocations" :key="allocation.id">
                                        <td class="px-4 py-3 text-gray-500">{{ index + 1 }}</td>
                                        <td class="px-4 py-3">{{ allocation.period }}</td>
                                        <td class="px-4 py-3">{{ allocation.sales_invoice_line?.sales_invoice?.invoice_number || '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(allocation.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Pool Biaya"
            message="Apakah Anda yakin ingin menghapus pool biaya ini? Tindakan ini tidak dapat dibatalkan."
            @close="showDeleteConfirmation = false"
            @confirm="deletePool"
        />
    </AuthenticatedLayout>
</template>
