<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    maintenance: Object,
    companies: Array,
    branches: Array,
    assets: Array,
    vendors: Array,
    filters: Object,
    maintenanceTypes: Object,
    statusOptions: Object,
});

const form = useForm({
    company_id: props.maintenance?.company_id || null,
    branch_id: props.maintenance?.branch_id || null,
    asset_id: props.maintenance?.asset_id || null,
    maintenance_date: props.maintenance?.maintenance_date || new Date().toISOString().split('T')[0],
    maintenance_type: props.maintenance?.maintenance_type || 'repair',
    description: props.maintenance?.description || '',
    vendor_id: props.maintenance?.vendor_id || null,
    labor_cost: props.maintenance?.labor_cost || 0,
    parts_cost: props.maintenance?.parts_cost || 0,
    external_cost: props.maintenance?.external_cost || 0,
    status: props.maintenance?.status || 'draft',
    notes: props.maintenance?.notes || '',
    create_another: false,
});

const submitted = ref(false);
const selectedCompany = ref(props.maintenance?.company_id || (props.companies.length === 1 ? props.companies[0].id : null));

const primaryCurrencySymbol = computed(() => {
    return page.props.primaryCurrency?.symbol || 'Rp';
});

const totalCost = computed(() => {
    const labor = parseFloat(form.labor_cost) || 0;
    const parts = parseFloat(form.parts_cost) || 0;
    const external = parseFloat(form.external_cost) || 0;
    return labor + parts + external;
});

const filteredAssets = computed(() => {
    if (!props.assets) return [];
    if (form.branch_id) {
        return props.assets.filter(asset => asset.branch_id === form.branch_id);
    }
    return props.assets;
});

watch(selectedCompany, (newCompanyId) => {
    router.reload({ only: ['branches', 'assets'], data: { company_id: newCompanyId } });
    form.company_id = newCompanyId;
    form.branch_id = null;
    form.asset_id = null;
}, { immediate: false });

watch(() => form.branch_id, (newBranchId) => {
    if (!props.maintenance && newBranchId) {
        form.asset_id = null;
    }
});

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.maintenance && newBranches && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

onMounted(() => {
    const initialCompanyId = props.maintenance?.company_id || (props.companies.length === 1 ? props.companies[0].id : null);
    selectedCompany.value = initialCompanyId;
    form.company_id = initialCompanyId;
    
    // Trigger initial data load if company is pre-selected but branches not loaded
    if (initialCompanyId && (!props.branches || props.branches.length === 0)) {
        router.reload({ only: ['branches', 'assets'], data: { company_id: initialCompanyId } });
    }
    
    if (!props.maintenance && props.branches && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
});

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    
    if (props.maintenance) {
        form.put(route('asset-maintenances.update', props.maintenance.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('asset-maintenances.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    form.company_id = selectedCompany.value;
                }
            },
            onError: () => {
                submitted.value = false;
            }
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :error="form.errors.company_id"
                        :disabled="!!props.maintenance"
                        required
                    />
                    
                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches.map(branch => ({ value: branch.id, label: branch.name }))"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.maintenance || !form.company_id"
                        required
                    />
                </div>
                
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.asset_id"
                        :options="filteredAssets.map(asset => ({ value: asset.id, label: `${asset.code} - ${asset.name}` }))"
                        label="Aset:"
                        placeholder="Pilih Aset"
                        :error="form.errors.asset_id"
                        :disabled="!!props.maintenance || !form.branch_id"
                        required
                    />
                    
                    <AppInput
                        v-model="form.maintenance_date"
                        type="date"
                        label="Tanggal Pemeliharaan:"
                        :error="form.errors.maintenance_date"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.maintenance_type"
                        :options="Object.entries(props.maintenanceTypes).map(([value, label]) => ({ value, label }))"
                        label="Jenis Pemeliharaan:"
                        :error="form.errors.maintenance_type"
                        required
                    />
                    
                    <AppSelect
                        v-model="form.vendor_id"
                        :options="props.vendors.map(vendor => ({ value: vendor.id, label: vendor.name }))"
                        label="Vendor:"
                        placeholder="Pilih Vendor (opsional)"
                        :error="form.errors.vendor_id"
                    />
                </div>

                <div class="mt-4">
                    <AppTextarea
                        v-model="form.description"
                        label="Deskripsi Pekerjaan:"
                        :error="form.errors.description"
                        required
                        rows="3"
                        placeholder="Jelaskan pekerjaan pemeliharaan yang dilakukan."
                    />
                </div>

                <div class="grid grid-cols-3 gap-4 mt-4">
                    <AppInput
                        v-model="form.labor_cost"
                        :numberFormat="true"
                        label="Biaya Tenaga Kerja:"
                        :prefix="primaryCurrencySymbol"
                        :error="form.errors.labor_cost"
                    />
                    
                    <AppInput
                        v-model="form.parts_cost"
                        :numberFormat="true"
                        label="Biaya Suku Cadang:"
                        :prefix="primaryCurrencySymbol"
                        :error="form.errors.parts_cost"
                    />
                    
                    <AppInput
                        v-model="form.external_cost"
                        :numberFormat="true"
                        label="Biaya Eksternal:"
                        :prefix="primaryCurrencySymbol"
                        :error="form.errors.external_cost"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="bg-gray-50 p-3 rounded border border-gray-200">
                        <span class="text-sm text-gray-600">Total Biaya:</span>
                        <span class="block text-lg font-semibold text-gray-900">{{ primaryCurrencySymbol }} {{ formatNumber(totalCost) }}</span>
                    </div>
                    
                    <AppSelect
                        v-model="form.status"
                        :options="Object.entries(props.statusOptions).map(([value, label]) => ({ value, label }))"
                        label="Status:"
                        :error="form.errors.status"
                        required
                    />
                </div>
                
                <div class="mt-4">
                    <AppTextarea
                        v-model="form.notes"
                        label="Catatan:"
                        :error="form.errors.notes"
                        rows="2"
                        placeholder="Catatan tambahan (opsional)."
                    />
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Pemeliharaan</h3>
                <p class="mb-2">Catat kegiatan pemeliharaan aset untuk tracking dan analisis biaya. Pastikan informasi yang dimasukkan akurat.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan dan cabang yang sesuai</li>
                    <li>Pilih aset yang akan dipelihara</li>
                    <li>Pilih jenis pemeliharaan yang dilakukan</li>
                    <li>Tentukan vendor jika melibatkan pihak ketiga</li>
                    <li>Isi biaya yang dikeluarkan per kategori</li>
                    <li>Jelaskan pekerjaan yang dilakukan secara detail</li>
                </ul>
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-blue-900 text-xs">
                        💡 Status "Selesai" akan mencatat biaya ke modul costing untuk analisis profitabilitas per aset.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.maintenance ? 'Simpan Perubahan' : 'Tambah Pemeliharaan' }}
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.maintenance" type="button" @click="submitForm(true)" class="mr-2">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('asset-maintenances.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
