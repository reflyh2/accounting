<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: Object,
    entry: Object,
});

const isAmortization = computed(() => props.asset.acquisition_type === 'fixed_rental');

function formatDate(date) {
    return date ? new Date(date).toLocaleDateString('id-ID') : '-';
}

const statusLabels = {
    'scheduled': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Terjadwal</span>',
    'processed': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Diproses</span>'
};

const typeLabels = {
    'depreciation': 'Penyusutan',
    'amortization': 'Amortisasi'
};
</script>

<template>
    <Head :title="isAmortization ? 'Detail Amortisasi Aset' : 'Detail Penyusutan Aset'" />

    <AuthenticatedLayout>
        <template #header>
            <h2>{{ isAmortization ? 'Detail Amortisasi Aset' : 'Detail Penyusutan Aset' }}</h2>
        </template>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="mb-6">
                        <AppBackLink :href="route('asset-depreciation.index', asset.id)" :text="isAmortization ? 'Kembali ke Daftar Amortisasi' : 'Kembali ke Daftar Penyusutan'" />
                    </div>

                    <h3 class="text-lg font-semibold mb-4">Informasi Aset</h3>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-600">Nama Aset</p>
                            <p class="font-medium">{{ asset?.name }}</p>
                        </div>
                        <div v-if="isAmortization">
                            <p class="text-sm text-gray-600">Nilai Sewa</p>
                            <p class="font-medium">{{ formatNumber(asset.rental_amount) }}</p>
                        </div>
                        <div v-else>
                            <p class="text-sm text-gray-600">Nilai Perolehan</p>
                            <p class="font-medium">{{ formatNumber(asset.purchase_cost) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Perusahaan</p>
                            <p class="font-medium">{{ asset.branch.branch_group.company.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Cabang</p>
                            <p class="font-medium">{{ asset.branch.name }}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-4">
                        {{ isAmortization ? 'Detail Amortisasi' : 'Detail Penyusutan' }}
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Tanggal Entri</p>
                            <p class="font-medium">{{ formatDate(entry.entry_date) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tipe</p>
                            <p class="font-medium">{{ typeLabels[entry.type] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Periode Awal</p>
                            <p class="font-medium">{{ formatDate(entry.period_start) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Periode Akhir</p>
                            <p class="font-medium">{{ formatDate(entry.period_end) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Jumlah</p>
                            <p class="font-medium">{{ formatNumber(entry.amount) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Akumulasi</p>
                            <p class="font-medium">{{ formatNumber(entry.cumulative_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Nilai Sisa</p>
                            <p class="font-medium">{{ formatNumber(entry.remaining_value) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Status</p>
                            <p class="font-medium" v-html="statusLabels[entry.status]"></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-600">Catatan</p>
                            <p class="font-medium">{{ entry.notes || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 