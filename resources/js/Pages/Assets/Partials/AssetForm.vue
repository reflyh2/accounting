<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { watch, computed } from 'vue';

const props = defineProps({
    asset: Object,
    companies: {
        type: Array,
        required: true
    },
    branches: {
        type: Array,
        required: true
    },
    categories: {
        type: Array,
        required: true
    },
    filters: {
        type: Object,
        default: () => ({})
    },
});

const form = useForm({
    name: props.asset?.name || '',
    branch_id: props.asset?.branch_id || '',
    category_id: props.asset?.category_id || '',
    asset_type: props.asset?.asset_type || 'tangible',
    acquisition_type: props.asset?.acquisition_type || 'purchase',
    serial_number: props.asset?.serial_number || '',
    status: props.asset?.status || 'active',
    purchase_cost: props.asset?.purchase_cost || '',
    purchase_date: props.asset ? new Date(props.asset.purchase_date).toISOString().split('T')[0] : '',
    supplier: props.asset?.supplier || '',
    warranty_expiry: props.asset?.warranty_expiry ? new Date(props.asset.warranty_expiry).toISOString().split('T')[0] : '',
    location: props.asset?.location || '',
    department: props.asset?.department || '',
    depreciation_method: props.asset?.depreciation_method || 'straight-line',
    useful_life_months: props.asset?.useful_life_months || '',
    salvage_value: props.asset?.salvage_value || 0,
    current_value: props.asset?.current_value || '',
    residual_value: props.asset?.residual_value || '',
    revaluation_method: props.asset?.revaluation_method || '',
    last_revaluation_date: props.asset?.last_revaluation_date ? new Date(props.asset.last_revaluation_date).toISOString().split('T')[0] : '',
    last_revaluation_amount: props.asset?.last_revaluation_amount || '',
    revaluation_notes: props.asset?.revaluation_notes || '',
    is_impaired: props.asset?.is_impaired || false,
    impairment_amount: props.asset?.impairment_amount || '',
    impairment_date: props.asset?.impairment_date ? new Date(props.asset.impairment_date).toISOString().split('T')[0] : '',
    impairment_notes: props.asset?.impairment_notes || '',
    notes: props.asset?.notes || '',
    create_another: false,
    down_payment: props.asset?.down_payment || '',
    financing_amount: props.asset?.financing_amount || '',
    interest_rate: props.asset?.interest_rate || '',
    financing_term_months: props.asset?.financing_term_months || '',
    first_payment_date: props.asset?.first_payment_date ? new Date(props.asset.first_payment_date).toISOString().split('T')[0] : '',
    first_depreciation_date: props.asset?.first_depreciation_date ? new Date(props.asset.first_depreciation_date).toISOString().split('T')[0] : '',
    payment_frequency: props.asset?.payment_frequency || '',
    rental_start_date: props.asset?.rental_start_date ? new Date(props.asset.rental_start_date).toISOString().split('T')[0] : '',
    rental_end_date: props.asset?.rental_end_date ? new Date(props.asset.rental_end_date).toISOString().split('T')[0] : '',
    rental_amount: props.asset?.rental_amount || '',
    rental_terms: props.asset?.rental_terms || '',
    rental_period: props.asset?.rental_period || '',
    amortization_term_months: props.asset?.amortization_term_months || '',
    first_amortization_date: props.asset?.first_amortization_date ? new Date(props.asset.first_amortization_date).toISOString().split('T')[0] : '',
});

// Format options for select inputs
const branchOptions = computed(() => {
    return props.branches.map(branch => ({
        value: branch.id,
        label: `${branch.name} (${branch.branch_group?.company?.name || 'No Company'})`
    }));
});

const categoryOptions = computed(() => {
    return props.categories.map(category => ({
        value: category.id,
        label: category.name
    }));
});

const assetTypes = [
    { value: 'tangible', label: 'Berwujud' },
    { value: 'intangible', label: 'Tidak Berwujud' },
];

const acquisitionTypes = [
    { value: 'outright_purchase', label: 'Pembelian Langsung' },
    { value: 'financed_purchase', label: 'Pembelian Kredit' },
    { value: 'fixed_rental', label: 'Sewa Periode Tetap' },
    { value: 'periodic_rental', label: 'Sewa Berkala' },
];

const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Tidak Aktif' },
    { value: 'maintenance', label: 'Pemeliharaan' },
    { value: 'disposed', label: 'Dilepas' },
];

const depreciationMethods = [
    { value: 'straight-line', label: 'Garis Lurus' },
    { value: 'declining-balance', label: 'Saldo Menurun' },
];

const showFinancingFields = computed(() => form.acquisition_type === 'financed_purchase');
const showRentalFields = computed(() => [
    'fixed_rental',
    'periodic_rental',
].includes(form.acquisition_type));
const showDepreciationFields = computed(() => [
    'outright_purchase',
    'financed_purchase'
].includes(form.acquisition_type));
const showPaymentFrequency = computed(() => form.acquisition_type === 'periodic_rental');

function resetForm() {
    form.reset();
    form.clearErrors();
}

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    if (props.asset) {
        form.put(route('assets.update', props.asset.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('assets.store'), {
            preserveScroll: true,
            onSuccess: (response) => {
                if (createAnother) {
                    resetForm();
                } else if (form.acquisition_type === 'lease') {
                    router.visit(route('asset-leases.create', response.data.id));
                }
            },
        });
    }
}

watch(() => form.acquisition_type, (newValue) => {
    if (newValue === 'lease') {
        form.notes = (form.notes || '') + '\nNote: You will be prompted to enter lease details after creating the asset.';
    }
});
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-2 gap-4">
                    <!-- Basic Information -->
                    <div class="col-span-2">
                        <h3 class="text-lg font-semibold mb-2">Informasi Dasar</h3>
                    </div>

                    <AppInput
                        v-model="form.name"
                        label="Nama Aset"
                        :error="form.errors.name"
                        required
                    />

                    <AppSelect
                        v-model="form.branch_id"
                        :options="branchOptions"
                        label="Cabang"
                        :error="form.errors.branch_id"
                        required
                    />

                    <AppSelect
                        v-model="form.category_id"
                        :options="categoryOptions"
                        label="Kategori"
                        :error="form.errors.category_id"
                        required
                    />

                    <AppSelect
                        v-model="form.asset_type"
                        :options="assetTypes"
                        label="Jenis Aset"
                        :error="form.errors.asset_type"
                        required
                    />

                    <AppSelect
                        v-model="form.acquisition_type"
                        :options="acquisitionTypes"
                        label="Jenis Perolehan"
                        :error="form.errors.acquisition_type"
                        required
                    />

                    <AppInput
                        v-model="form.serial_number"
                        label="Nomor Seri"
                        :error="form.errors.serial_number"
                    />

                    <!-- Purchase Information -->
                    <template v-if="!showRentalFields">
                        <div class="col-span-2 mt-4">
                            <h3 class="text-lg font-semibold mb-2">Informasi Pembelian</h3>
                        </div>

                        <AppInput
                            v-model="form.purchase_cost"
                            label="Harga Perolehan"
                            :number-format="true"
                            :error="form.errors.purchase_cost"
                            required
                        />

                        <AppInput
                            v-model="form.purchase_date"
                            type="date"
                            label="Tanggal Perolehan"
                            :error="form.errors.purchase_date"
                            required
                        />
                    </template>

                    <!-- Financing Information -->
                    <template v-if="showFinancingFields">
                        <div class="col-span-2 mt-4">
                            <h3 class="text-lg font-semibold mb-2">Informasi Pembiayaan</h3>
                        </div>

                        <AppInput
                            v-model="form.down_payment"
                            label="Uang Muka"
                            :number-format="true"
                            :error="form.errors.down_payment"
                            required
                        />

                        <AppInput
                            v-model="form.financing_amount"
                            label="Jumlah Pembiayaan"
                            :number-format="true"
                            :error="form.errors.financing_amount"
                            required
                        />

                        <AppInput
                            v-model="form.interest_rate"
                            label="Suku Bunga (%)"
                            type="number"
                            step="0.01"
                            :error="form.errors.interest_rate"
                            required
                        />

                        <AppInput
                            v-model="form.financing_term_months"
                            label="Jangka Waktu (Bulan)"
                            type="number"
                            :error="form.errors.financing_term_months"
                            required
                        />

                        <AppInput
                            v-model="form.first_payment_date"
                            type="date"
                            label="Tanggal Pembayaran Pertama"
                            :error="form.errors.first_payment_date"
                            required
                        />
                    </template>

                    <!-- Rental Information -->
                    <template v-if="['fixed_rental', 'periodic_rental', 'casual_rental'].includes(form.acquisition_type)">
                        <div class="col-span-2 mt-4">
                            <h3 class="text-lg font-semibold mb-2">Informasi Sewa</h3>
                        </div>

                        <!-- Fields for fixed_rental -->
                        <template v-if="form.acquisition_type === 'fixed_rental'">
                            <AppInput
                                v-model="form.rental_start_date"
                                type="date"
                                label="Tanggal Mulai Sewa"
                                :error="form.errors.rental_start_date"
                            />
                            <AppInput
                                v-model="form.rental_end_date"
                                type="date"
                                label="Tanggal Selesai Sewa"
                                :error="form.errors.rental_end_date"
                            />
                            <AppInput
                                v-model="form.rental_amount"
                                type="number"
                                label="Biaya Sewa"
                                :error="form.errors.rental_amount"
                            />
                            <AppInput
                                v-model="form.amortization_term_months"
                                type="number"
                                label="Masa Amortisasi (Bulan)"
                                :error="form.errors.amortization_term_months"
                            />
                            <AppInput
                                v-model="form.first_amortization_date"
                                type="date"
                                label="Tanggal Mulai Amortisasi"
                                :error="form.errors.first_amortization_date"
                            />
                        </template>

                        <!-- Fields for periodic_rental -->
                        <template v-if="form.acquisition_type === 'periodic_rental'">
                            <AppInput
                                v-model="form.rental_start_date"
                                type="date"
                                label="Tanggal Mulai Sewa"
                                :error="form.errors.rental_start_date"
                                required
                            />

                            <AppInput
                                v-model="form.rental_end_date"
                                type="date"
                                label="Tanggal Selesai Sewa"
                                :error="form.errors.rental_end_date"
                                required
                            />

                            <AppSelect
                                v-model="form.payment_frequency"
                                :options="[
                                    { value: 'monthly', label: 'Bulanan' },
                                    { value: 'quarterly', label: 'Per 3 Bulan' },
                                    { value: 'annually', label: 'Tahunan' }
                                ]"
                                label="Frekuensi Pembayaran"
                                :error="form.errors.payment_frequency"
                                required
                            />

                            <AppInput
                                v-model="form.rental_amount"
                                label="Biaya Sewa per Periode"
                                :number-format="true"
                                :error="form.errors.rental_amount"
                                required
                            />
                        </template>

                        <AppTextarea
                            v-model="form.rental_terms"
                            label="Ketentuan Sewa"
                            :error="form.errors.rental_terms"
                            class="col-span-2"
                        />
                    </template>

                    <!-- Depreciation/Amortization Information (for owned assets) -->
                    <template v-if="['outright_purchase', 'financed_purchase'].includes(form.acquisition_type)">
                        <div class="col-span-2 mt-4">
                            <h3 class="text-lg font-semibold mb-2">Informasi Penyusutan/Amortisasi</h3>
                        </div>

                        <AppSelect
                            v-model="form.depreciation_method"
                            :options="[
                                { value: 'straight-line', label: 'Garis Lurus' },
                                { value: 'declining-balance', label: 'Saldo Menurun' }
                            ]"
                            label="Metode Penyusutan"
                            :error="form.errors.depreciation_method"
                            required
                        />

                        <AppInput
                            v-model="form.useful_life_months"
                            label="Masa Manfaat (Bulan)"
                            type="number"
                            :error="form.errors.useful_life_months"
                            required
                        />

                        <AppInput
                            v-model="form.salvage_value"
                            label="Nilai Sisa"
                            :number-format="true"
                            :error="form.errors.salvage_value"
                            required
                        />

                        <AppInput
                            v-model="form.first_depreciation_date"
                            type="date"
                            label="Tanggal Penyusutan Pertama"
                            :error="form.errors.first_depreciation_date"
                            required
                        />
                    </template>

                    <!-- Location and Status -->
                    <div class="col-span-2 mt-4">
                        <h3 class="text-lg font-semibold mb-2">Lokasi dan Status</h3>
                    </div>

                    <AppInput
                        v-model="form.location"
                        label="Lokasi"
                        :error="form.errors.location"
                    />

                    <AppInput
                        v-model="form.department"
                        label="Departemen"
                        :error="form.errors.department"
                    />

                    <AppSelect
                        v-model="form.status"
                        :options="statusOptions"
                        label="Status"
                        :error="form.errors.status"
                        required
                    />

                    <!-- Additional Information -->
                    <div class="col-span-2 mt-4">
                        <h3 class="text-lg font-semibold mb-2">Informasi Tambahan</h3>
                    </div>

                    <AppInput
                        v-model="form.supplier"
                        label="Supplier"
                        :error="form.errors.supplier"
                    />

                    <AppInput
                        v-model="form.warranty_expiry"
                        type="date"
                        label="Warranty Expiry"
                        :error="form.errors.warranty_expiry"
                    />

                    <!-- Revaluation Information -->
                    <!-- <div class="col-span-2 mt-4">
                        <h3 class="text-lg font-semibold mb-2">Informasi Revaluasi</h3>
                    </div>

                    <AppInput
                        v-model="form.revaluation_method"
                        label="Metode Revaluasi"
                        :error="form.errors.revaluation_method"
                    />

                    <AppInput
                        v-model="form.last_revaluation_date"
                        type="date"
                        label="Tanggal Revaluasi Terakhir"
                        :error="form.errors.last_revaluation_date"
                    />

                    <AppInput
                        v-model="form.last_revaluation_amount"
                        label="Nilai Revaluasi Terakhir"
                        :number-format="true"
                        :error="form.errors.last_revaluation_amount"
                    />

                    <div class="col-span-2">
                        <AppTextarea
                            v-model="form.revaluation_notes"
                            label="Catatan Revaluasi"
                            :error="form.errors.revaluation_notes"
                        />
                    </div> -->

                    <!-- Impairment Information -->
                    <!-- <div class="col-span-2 mt-4">
                        <h3 class="text-lg font-semibold mb-2">Informasi Penurunan Nilai</h3>
                    </div>

                    <div class="col-span-2">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                v-model="form.is_impaired"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            >
                            <span class="ml-2">Mengalami Penurunan Nilai</span>
                        </label>
                    </div>

                    <template v-if="form.is_impaired">
                        <AppInput
                            v-model="form.impairment_amount"
                            label="Jumlah Penurunan Nilai"
                            :number-format="true"
                            :error="form.errors.impairment_amount"
                            required
                        />

                        <AppInput
                            v-model="form.impairment_date"
                            type="date"
                            label="Tanggal Penurunan Nilai"
                            :error="form.errors.impairment_date"
                            required
                        />

                        <div class="col-span-2">
                            <AppTextarea
                                v-model="form.impairment_notes"
                                label="Catatan Penurunan Nilai"
                                :error="form.errors.impairment_notes"
                            />
                        </div>
                    </template> -->

                    <!-- Notes -->
                    <div class="col-span-2">
                        <AppTextarea
                            v-model="form.notes"
                            label="Catatan Tambahan"
                            :error="form.errors.notes"
                        />
                    </div>
                </div>

                <div class="mt-4 flex items-center">
                    <AppPrimaryButton type="submit" class="mr-2">
                        {{ asset ? 'Ubah Aset' : 'Tambah Aset' }}
                    </AppPrimaryButton>
                    <AppUtilityButton v-if="!asset" type="button" @click="submitForm(true)" class="mr-2">
                        Tambah & Buat Lagi
                    </AppUtilityButton>
                    <AppSecondaryButton @click="$inertia.visit(route('assets.index', filters))">
                        Batal
                    </AppSecondaryButton>
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Aset</h3>
                <p class="mb-2">Silakan lengkapi informasi berikut:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Informasi dasar aset termasuk jenis dan kategori</li>
                    <li>Detail keuangan termasuk biaya dan metode penyusutan</li>
                    <li>Informasi lokasi dan status</li>
                    <!-- <li>Detail revaluasi atau penurunan nilai jika ada</li> -->
                    <li>Catatan atau dokumentasi tambahan</li>
                </ul>
            </div>
        </div>
    </form>
</template> 