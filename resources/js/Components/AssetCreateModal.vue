<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { XMarkIcon } from '@heroicons/vue/24/solid';
import axios from 'axios';

const props = defineProps({
    show: Boolean,
    companyId: [String, Number],
    companies: Array,
    branches: Array,
    categories: Array,
});

const emit = defineEmits(['close', 'asset-created', 'notification']);

const form = useForm({
    company_id: props.companyId || null,
    branch_id: null,
    asset_category_id: null,
    name: '',
    type: 'tangible',
    acquisition_type: 'outright_purchase',
    acquisition_date: new Date().toISOString().split('T')[0],
    cost_basis: 0,
    salvage_value: 0,
    is_depreciable: true,
    is_amortizable: false,
    depreciation_method: 'straight-line',
    useful_life_months: 60,
    depreciation_start_date: new Date().toISOString().split('T')[0],
    accumulated_depreciation: 0,
    net_book_value: null,
    status: 'active',
    notes: '',
    warranty_expiry: null,
});

const availableBranches = ref([]);
const loading = ref(false);

const assetTypes = {
    'tangible': 'Aset Berwujud',
    'intangible': 'Aset Tidak Berwujud'
};

const acquisitionTypes = {
    'outright_purchase': 'Pembelian Langsung',
    'installment_purchase': 'Pembelian Cicilan',
    'lease': 'Sewa',
    'gift': 'Hibah',
    'construction': 'Pembangunan',
    'other': 'Lainnya'
};

const depreciationMethods = {
    'straight-line': 'Garis Lurus',
    'declining-balance': 'Saldo Menurun',
    'units-of-production': 'Unit Produksi'
};

const statusOptions = {
    'active': 'Aktif',
    'inactive': 'Tidak Aktif',
    'disposed': 'Sudah Dilepas',
    'under_maintenance': 'Dalam Perawatan'
};

// Calculate net book value
const netBookValue = computed(() => {
    const cost = parseFloat(form.cost_basis) || 0;
    const accumulatedDepreciation = parseFloat(form.accumulated_depreciation) || 0;
    return cost - accumulatedDepreciation;
});

watch(() => [form.cost_basis, form.accumulated_depreciation], () => {
    form.net_book_value = netBookValue.value;
}, { deep: true });

// Watch company changes to load branches
watch(() => form.company_id, async (newCompanyId) => {
    if (newCompanyId) {
        try {
            const response = await axios.get(route('api.branches-by-company', newCompanyId));
            availableBranches.value = Array.isArray(response.data.availableBranches) ? response.data.availableBranches : [];
            if (availableBranches.value.length === 1) {
                form.branch_id = availableBranches.value[0].id;
            } else {
                form.branch_id = null;
            }
        } catch (error) {
            console.error('Error loading branches:', error);
            availableBranches.value = []; // Ensure it's always an array
            form.branch_id = null;
        }
    } else {
        availableBranches.value = [];
        form.branch_id = null;
    }
}, { immediate: true });

// Reset form when modal is shown
watch(() => props.show, (show) => {
    if (show) {
        form.reset();
        form.company_id = props.companyId || null;
        form.type = 'tangible';
        form.acquisition_type = 'outright_purchase';
        form.acquisition_date = new Date().toISOString().split('T')[0];
        form.cost_basis = 0;
        form.salvage_value = 0;
        form.is_depreciable = true;
        form.is_amortizable = false;
        form.depreciation_method = 'straight-line';
        form.useful_life_months = 60;
        form.depreciation_start_date = new Date().toISOString().split('T')[0];
        form.accumulated_depreciation = 0;
        form.status = 'active';
        form.clearErrors();
    }
});

async function submitForm() {
    loading.value = true;
    
    try {
        const response = await axios.post(route('assets.ajax-store'), form.data());
        
        emit('asset-created', response.data.asset);
        emit('close');
        
        // Emit success notification instead of alert
        emit('notification', {
            type: 'success',
            message: 'Aset berhasil ditambahkan!'
        });
        
    } catch (error) {
        if (error.response?.data?.errors) {
            // Set validation errors
            form.setError(error.response.data.errors);
        } else {
            // Emit error notification instead of alert
            emit('notification', {
                type: 'error',
                message: 'Terjadi kesalahan saat menyimpan aset'
            });
        }
    } finally {
        loading.value = false;
    }
}

function closeModal() {
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[110] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>

                <!-- Modal content -->
                <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full sm:p-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Tambah Aset Baru</h3>
                        <button
                            @click="closeModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <XMarkIcon class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Form -->
                    <form @submit.prevent="submitForm" class="space-y-4 modal-form">
                        <div class="grid grid-cols-2 gap-4">
                            <AppSelect
                                v-model="form.company_id"
                                :options="companies.map(company => ({ value: company.id, label: company.name }))"
                                label="Perusahaan:"
                                placeholder="Pilih Perusahaan"
                                :error="form.errors.company_id"
                                required
                                class="modal-select"
                            />
                            
                            <AppSelect
                                v-model="form.branch_id"
                                :options="(availableBranches || []).map(branch => ({ value: branch.id, label: branch.name }))"
                                label="Cabang:"
                                placeholder="Pilih Cabang"
                                :error="form.errors.branch_id"
                                required
                                class="modal-select"
                            />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <AppSelect
                                v-model="form.asset_category_id"
                                :options="categories.map(category => ({ value: category.id, label: category.name }))"
                                label="Kategori Aset:"
                                placeholder="Pilih Kategori"
                                :error="form.errors.asset_category_id"
                                required
                                class="modal-select"
                            >
                            </AppSelect>
                            
                            <AppInput
                                v-model="form.name"
                                label="Nama Aset:"
                                :error="form.errors.name"
                                required
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <AppSelect
                                v-model="form.type"
                                :options="Object.entries(assetTypes).map(([value, label]) => ({ value, label }))"
                                label="Jenis Aset:"
                                :error="form.errors.type"
                                required
                                class="modal-select"
                            />
                            
                            <AppSelect
                                v-model="form.acquisition_type"
                                :options="Object.entries(acquisitionTypes).map(([value, label]) => ({ value, label }))"
                                label="Cara Perolehan:"
                                :error="form.errors.acquisition_type"
                                required
                                class="modal-select"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <AppInput
                                v-model="form.acquisition_date"
                                type="date"
                                label="Tanggal Perolehan:"
                                :error="form.errors.acquisition_date"
                            />
                            
                            <AppInput
                                v-model="form.cost_basis"
                                :numberFormat="true"
                                label="Nilai Perolehan:"
                                :error="form.errors.cost_basis"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <AppInput
                                v-model="form.salvage_value"
                                :numberFormat="true"
                                label="Nilai Residu:"
                                :error="form.errors.salvage_value"
                            />
                            
                            <AppSelect
                                v-model="form.status"
                                :options="Object.entries(statusOptions).map(([value, label]) => ({ value, label }))"
                                label="Status:"
                                :error="form.errors.status"
                                required
                                class="modal-select"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Dapat Disusutkan:</label>
                                <div class="flex items-center mt-2">
                                    <input 
                                        type="checkbox" 
                                        v-model="form.is_depreciable" 
                                        class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
                                    />
                                    <span class="ml-2 text-sm text-gray-600">{{ form.is_depreciable ? 'Ya' : 'Tidak' }}</span>
                                </div>
                                <p v-if="form.errors.is_depreciable" class="mt-1 text-sm text-red-600">{{ form.errors.is_depreciable }}</p>
                            </div>
                            
                            <div class="flex flex-col">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Dapat Diamortisasi:</label>
                                <div class="flex items-center mt-2">
                                    <input 
                                        type="checkbox" 
                                        v-model="form.is_amortizable" 
                                        class="rounded border-gray-300 text-main-600 shadow-sm focus:border-main-300 focus:ring focus:ring-main-200 focus:ring-opacity-50"
                                    />
                                    <span class="ml-2 text-sm text-gray-600">{{ form.is_amortizable ? 'Ya' : 'Tidak' }}</span>
                                </div>
                                <p v-if="form.errors.is_amortizable" class="mt-1 text-sm text-red-600">{{ form.errors.is_amortizable }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <AppSelect
                                v-model="form.depreciation_method"
                                :options="Object.entries(depreciationMethods).map(([value, label]) => ({ value, label }))"
                                label="Metode Penyusutan:"
                                :error="form.errors.depreciation_method"
                                required
                                class="modal-select"
                            />
                            
                            <AppInput
                                v-model="form.useful_life_months"
                                type="number"
                                label="Umur Ekonomis (Bulan):"
                                :error="form.errors.useful_life_months"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <AppInput
                                v-model="form.depreciation_start_date"
                                type="date"
                                label="Tanggal Mulai Penyusutan:"
                                :error="form.errors.depreciation_start_date"
                            />
                            
                            <AppInput
                                v-model="form.accumulated_depreciation"
                                :numberFormat="true"
                                label="Akumulasi Penyusutan:"
                                :error="form.errors.accumulated_depreciation"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <AppInput
                                v-model="form.net_book_value"
                                :numberFormat="true"
                                label="Nilai Buku:"
                                :error="form.errors.net_book_value"
                                disabled
                            />
                            
                            <AppInput
                                v-model="form.warranty_expiry"
                                type="date"
                                label="Kadaluwarsa Garansi:"
                                :error="form.errors.warranty_expiry"
                            />
                        </div>
                        
                        <AppTextarea
                            v-model="form.notes"
                            label="Catatan:"
                            :error="form.errors.notes"
                        />

                        <!-- Action buttons -->
                        <div class="flex justify-end space-x-3 pt-4">
                            <AppSecondaryButton @click="closeModal" :disabled="loading">
                                Batal
                            </AppSecondaryButton>
                            <AppPrimaryButton type="submit" :disabled="loading">
                                {{ loading ? 'Menyimpan...' : 'Simpan Aset' }}
                            </AppPrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* Ensure AppSelect dropdowns appear above modal content */
/* Since AppSelect uses Teleport to body, we need to use global styles */
</style>

<style>
/* Global styles to ensure select dropdowns appear above modal */
.fixed.z-50.bg-white.shadow-lg.ring-1.ring-black.ring-opacity-5 {
    z-index: 9999 !important;
}

/* Alternative approach - target all fixed dropdowns when modal is present */
body .fixed.bg-white.shadow-lg {
    z-index: 9999 !important;
}
</style> 