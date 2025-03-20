<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppModal from '@/Components/AppModal.vue';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import AppTransferButton from '@/Components/AppTransferButton.vue';
import AppMaintenanceButton from '@/Components/AppMaintenanceButton.vue';
import AppPaymentButton from '@/Components/AppPaymentButton.vue';
import AppDisposeButton from '@/Components/AppDisposeButton.vue';
import AppDepreciationButton from '@/Components/AppDepreciationButton.vue';
const props = defineProps({
    asset: {
        type: Object,
        required: true
    },
    currentValue: {
        type: Number,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    },
});

const confirmingAssetDeletion = ref(false);

const acquisitionType = {
    'outright_purchase': 'Pembelian Langsung',
    'financed_purchase': 'Pembelian Kredit',
    'fixed_rental': 'Sewa Periode Tetap',
    'periodic_rental': 'Sewa Berkala',
    'casual_rental': 'Sewa Sekali Pakai',
};

const assetType = {
    'tangible': 'Berwujud',
    'intangible': 'Tidak Berwujud',
};

function deleteAsset() {
    router.delete(route('assets.destroy', props.asset.id), {
        preserveScroll: true,
    });
}

const getStatusClass = (status) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'inactive':
            return 'bg-gray-100 text-gray-800';
        case 'maintenance':
            return 'bg-yellow-100 text-yellow-800';
        case 'disposed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const formatCurrency = (value) => {
    if (!value && value !== 0) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString();
};

const getNestedValue = (obj, path) => {
    if (!obj) return '-';
    return path.split('.').reduce((acc, part) => acc && acc[part], obj) ?? '-';
};
</script>

<template>
    <Head title="Detail Aset" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Aset</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('assets.index', filters)" text="Kembali ke Daftar Aset" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">{{ asset.name }}</h3>
                            <div class="flex items-center gap-2">
                                <template v-if="asset">
                                    <!-- Asset Management Actions -->
                                    <AppPaymentButton
                                        v-if="['outright_purchase', 'financed_purchase'].includes(asset.acquisition_type)"
                                        @click="$inertia.visit(route('asset-financing-payments.index', asset.id))"
                                        title="Kelola Pembayaran Pembiayaan"
                                    />
                                    <AppPaymentButton
                                        v-if="['fixed_rental', 'periodic_rental', 'casual_rental'].includes(asset.acquisition_type)"
                                        @click="$inertia.visit(route('asset-rental-payments.index', asset.id))"
                                        title="Kelola Pembayaran Sewa"
                                    />
                                    <AppDepreciationButton
                                        v-if="['outright_purchase', 'financed_purchase', 'fixed_rental'].includes(asset.acquisition_type)"
                                        @click="$inertia.visit(route('asset-depreciation.index', asset.id))"
                                        :title="['fixed_rental'].includes(asset.acquisition_type) ? 'Amortisasi Aset' : 'Penyusutan Aset'"
                                    />
                                    <AppTransferButton
                                        v-if="asset.status !== 'disposed'"
                                        @click="$inertia.visit(route('asset-transfers.create', asset.id))"
                                        title="Transfer Aset"
                                    />
                                    <AppDisposeButton
                                        v-if="asset.status !== 'disposed'"
                                        @click="$inertia.visit(route('asset-disposals.create', asset.id))"
                                        title="Lepas Aset"
                                    />
                                    <AppMaintenanceButton
                                        @click="$inertia.visit(route('asset-maintenance.index', asset.id))"
                                        title="Catatan Pemeliharaan"
                                    />

                                    <!-- Basic Actions -->
                                    <AppEditButton
                                        @click="$inertia.visit(route('assets.edit', asset.id))"
                                        title="Ubah Aset"
                                    />
                                    <AppDeleteButton 
                                        @click="confirmingAssetDeletion = true"
                                        title="Hapus Aset"
                                    />
                                </template>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <!-- Basic Information -->
                            <div>
                                <h3 class="text-md font-semibold mb-3">Informasi Dasar</h3>
                                <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                    <div>
                                        <dt class="font-semibold text-gray-500">Nama</dt>
                                        <dd>{{ asset.name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Kategori</dt>
                                        <dd>{{ getNestedValue(asset, 'category.name') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Jenis Aset</dt>
                                        <dd class="capitalize">{{ assetType[asset.asset_type] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Jenis Perolehan</dt>
                                        <dd class="capitalize">{{ acquisitionType[asset.acquisition_type] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Nomor Seri</dt>
                                        <dd>{{ asset.serial_number || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Status</dt>
                                        <dd>
                                            <span :class="[
                                                'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                                getStatusClass(asset.status)
                                            ]">
                                                {{ asset.status === 'active' ? 'Aktif' :
                                                    asset.status === 'inactive' ? 'Tidak Aktif' :
                                                    asset.status === 'maintenance' ? 'Pemeliharaan' :
                                                    'Dilepas' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Purchase Information -->
                            <div v-if="['outright_purchase', 'financed_purchase'].includes(asset.acquisition_type)">
                                <h3 class="text-md font-semibold mb-3">Informasi Pembelian</h3>
                                <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                    <div>
                                        <dt class="font-semibold text-gray-500">Harga Perolehan</dt>
                                        <dd>{{ formatCurrency(asset.purchase_cost) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Tanggal Perolehan</dt>
                                        <dd>{{ formatDate(asset.purchase_date) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Pemasok</dt>
                                        <dd>{{ asset.supplier || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Masa Garansi</dt>
                                        <dd>{{ formatDate(asset.warranty_expiry) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Rental Information -->
                            <div v-if="asset.acquisition_type === 'fixed_rental' || asset.acquisition_type === 'periodic_rental'" class="mt-6">
                                <h3 class="text-md font-semibold mb-3">Informasi Sewa</h3>
                                <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Tanggal Mulai Sewa</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ asset.rental_start_date ? new Date(asset.rental_start_date).toLocaleDateString('id-ID') : '-' }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Tanggal Selesai Sewa</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ asset.rental_end_date ? new Date(asset.rental_end_date).toLocaleDateString('id-ID') : '-' }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Biaya Sewa</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatCurrency(asset.rental_amount) }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Periode Sewa</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ asset.rental_period || '-' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Location Information -->
                            <div>
                                <h3 class="text-md font-semibold mb-3">Informasi Lokasi</h3>
                                <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                    <div>
                                        <dt class="font-semibold text-gray-500">Cabang</dt>
                                        <dd>{{ getNestedValue(asset, 'branch.name') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Departemen</dt>
                                        <dd>{{ asset.department || '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Lokasi</dt>
                                        <dd>{{ asset.location || '-' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Depreciation Information -->
                            <div v-if="['outright_purchase', 'financed_purchase'].includes(asset.acquisition_type)">
                                <h3 class="text-md font-semibold mb-3">Informasi Penyusutan</h3>
                                <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                    <div>
                                        <dt class="font-semibold text-gray-500">Metode Penyusutan</dt>
                                        <dd>{{ asset.depreciation_method === 'straight-line' ? 'Garis Lurus' : 'Saldo Menurun' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Masa Manfaat</dt>
                                        <dd>{{ asset.useful_life_months }} bulan</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Nilai Sisa</dt>
                                        <dd>{{ formatCurrency(asset.salvage_value) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-gray-500">Nilai Sekarang</dt>
                                        <dd>{{ formatCurrency(currentValue) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Amortization Information for Fixed Rental -->
                            <div v-if="asset.acquisition_type === 'fixed_rental'">
                                <h3 class="text-md font-semibold mb-3">Informasi Amortisasi</h3>
                                <dl class="grid grid-cols-1 gap-4 *:py-1 text-sm">
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Biaya Sewa</dt>
                                        <dd>{{ formatCurrency(asset.rental_amount) }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Masa Amortisasi</dt>
                                        <dd>{{ asset.amortization_term_months }} bulan</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Tanggal Mulai Amortisasi</dt>
                                        <dd>{{ asset.first_amortization_date ? new Date(asset.first_amortization_date).toLocaleDateString('id-ID') : '-' }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Amortisasi Terakumulasi</dt>
                                        <dd>{{ formatCurrency(asset.accumulated_amortization) }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Tanggal Amortisasi Terakhir</dt>
                                        <dd>{{ asset.last_amortization_date ? new Date(asset.last_amortization_date).toLocaleDateString('id-ID') : '-' }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="font-semibold text-gray-500">Nilai Buku</dt>
                                        <dd>{{ formatCurrency(asset.rental_amount - (asset.accumulated_amortization || 0)) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <AppModal 
            :show="confirmingAssetDeletion" 
            @close="confirmingAssetDeletion = false"
            :closeable="true"
        >
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Apakah Anda yakin ingin menghapus aset ini?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Tindakan ini tidak dapat dibatalkan.
                </p>

                <div class="mt-6 flex justify-end">
                    <AppSecondaryButton @click="confirmingAssetDeletion = false" class="mr-3">
                        Batal
                    </AppSecondaryButton>

                    <AppDangerButton @click="deleteAsset">
                        Hapus Aset
                    </AppDangerButton>
                </div>
            </div>
        </AppModal>
    </AuthenticatedLayout>
</template> 