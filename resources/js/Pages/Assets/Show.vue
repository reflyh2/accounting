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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Detail Aset</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 flex justify-between items-center">
                        <AppBackLink :href="route('assets.index', filters)" text="Kembali ke Daftar Aset" />
                        <div class="flex items-center gap-2">
                            <template v-if="asset">
                                <!-- Asset Management Actions -->
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Informasi Dasar</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Nama</dt>
                                    <dd>{{ asset.name }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Kategori</dt>
                                    <dd>{{ getNestedValue(asset, 'category.name') }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Jenis Aset</dt>
                                    <dd class="capitalize">{{ assetType[asset.asset_type] }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Jenis Perolehan</dt>
                                    <dd class="capitalize">{{ acquisitionType[asset.acquisition_type] }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Nomor Seri</dt>
                                    <dd>{{ asset.serial_number || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Status</dt>
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
                            <h3 class="text-lg font-semibold mb-4">Informasi Pembelian</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Harga Perolehan</dt>
                                    <dd>{{ formatCurrency(asset.purchase_cost) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Tanggal Perolehan</dt>
                                    <dd>{{ formatDate(asset.purchase_date) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Pemasok</dt>
                                    <dd>{{ asset.supplier || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Masa Garansi</dt>
                                    <dd>{{ formatDate(asset.warranty_expiry) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Rental Information -->
                        <div v-if="['fixed_rental', 'periodic_rental'].includes(asset.acquisition_type)">
                            <h3 class="text-lg font-semibold mb-4">Informasi Sewa</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Tanggal Mulai Sewa</dt>
                                    <dd>{{ formatDate(asset.rental_start_date) }}</dd>
                                </div>
                                <div v-if="asset.acquisition_type === 'fixed_rental'">
                                    <dt class="font-medium">Tanggal Selesai Sewa</dt>
                                    <dd>{{ formatDate(asset.rental_end_date) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Biaya Sewa</dt>
                                    <dd>{{ formatCurrency(asset.rental_amount) }}</dd>
                                </div>
                                <div v-if="asset.acquisition_type === 'periodic_rental'">
                                    <dt class="font-medium">Frekuensi Pembayaran</dt>
                                    <dd>{{ {
                                        'monthly': 'Bulanan',
                                        'quarterly': 'Per 3 Bulan',
                                        'annually': 'Tahunan'
                                    }[asset.payment_frequency] || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Ketentuan Sewa</dt>
                                    <dd>{{ asset.rental_terms || '-' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Location Information -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Informasi Lokasi</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Cabang</dt>
                                    <dd>{{ getNestedValue(asset, 'branch.name') }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Departemen</dt>
                                    <dd>{{ asset.department || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Lokasi</dt>
                                    <dd>{{ asset.location || '-' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Depreciation Information -->
                        <div v-if="['outright_purchase', 'financed_purchase'].includes(asset.acquisition_type)">
                            <h3 class="text-lg font-semibold mb-4">Informasi Penyusutan</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                <div>
                                    <dt class="font-medium">Metode Penyusutan</dt>
                                    <dd>{{ asset.depreciation_method === 'straight-line' ? 'Garis Lurus' : 'Saldo Menurun' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Masa Manfaat</dt>
                                    <dd>{{ asset.useful_life_months }} bulan</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Nilai Sisa</dt>
                                    <dd>{{ formatCurrency(asset.salvage_value) }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium">Nilai Sekarang</dt>
                                    <dd>{{ formatCurrency(currentValue) }}</dd>
                                </div>
                            </dl>
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