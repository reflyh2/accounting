<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { router } from '@inertiajs/vue3';
import { statusOptions } from '@/constants/assetStatus';

const props = defineProps({
    asset: Object,
    categories: Array,
    companies: Array,
    branches: Array,
    filters: Object,
});

const form = useForm({
    name: props.asset?.name || '',
    company_id: props.asset?.branch?.branch_group?.company_id || '',
    branch_id: props.asset?.branch_id || '',
    category_id: props.asset?.category_id || '',
    serial_number: props.asset?.serial_number || '',
    status: props.asset?.status || 'active',
    purchase_cost: props.asset?.purchase_cost || '',
    purchase_date: props.asset ?    new Date(props.asset?.purchase_date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    supplier: props.asset?.supplier || '',
    warranty_expiry: props.asset ? new Date(props.asset?.warranty_expiry).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    depreciation_method: props.asset?.depreciation_method || 'straight-line',
    useful_life_months: props.asset?.useful_life_months || '',
    salvage_value: props.asset?.salvage_value || '',
    notes: props.asset?.notes || '',
    create_another: false,
});

const selectedCompany = ref(props.asset?.branch?.branch_group?.company_id || (props.companies?.length === 1 ? props.companies[0].id : null));

watch(selectedCompany, (newCompanyId) => {
    if (!props.asset) {
        router.reload({ 
            only: ['branches', 'categories'], 
            data: { company_id: newCompanyId } 
        });
        form.branch_id = '';
        form.category_id = '';
    }
});

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.journal && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true }
);

const depreciationMethods = [
    { value: 'straight-line', label: 'Garis Lurus' },
    { value: 'declining-balance', label: 'Saldo Menurun' },
];

function submitForm(createAnother = false) {
    form.company_id = selectedCompany.value;
    form.create_another = createAnother;
    
    if (props.asset) {
        form.put(route('assets.update', props.asset.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    } else {
        form.post(route('assets.store'), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4"> 
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="props.companies?.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan"
                        :error="form.errors.company_id"
                        :disabled="!!props.asset"
                        required
                    />

                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches?.map(branch => ({ value: branch.id, label: branch.name }))"
                        label="Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.asset"
                        required
                    />

                    <AppInput
                        v-model="form.name"
                        label="Nama Aset"
                        :error="form.errors.name"
                        required
                    />

                    <AppSelect
                        v-model="form.category_id"
                        :options="categories.map(category => ({ value: category.id, label: category.name }))"
                        label="Kategori"
                        :error="form.errors.category_id"
                        required
                    />

                    <AppInput
                        v-model="form.serial_number"
                        label="Nomor Seri Aset"
                        :error="form.errors.serial_number"
                    />

                    <AppSelect
                        v-model="form.status"
                        :options="statusOptions"
                        label="Status"
                        :error="form.errors.status"
                        required
                    />

                    <AppInput
                        v-model="form.purchase_cost"
                        label="Harga Pembelian"
                        :numberFormat="true"
                        :error="form.errors.purchase_cost"
                        required
                    />

                    <AppInput
                        v-model="form.purchase_date"
                        label="Tanggal Pembelian"
                        type="date"
                        :error="form.errors.purchase_date"
                        required
                    />

                    <AppInput
                        v-model="form.supplier"
                        label="Supplier"
                        :error="form.errors.supplier"
                    />

                    <AppInput
                        v-model="form.warranty_expiry"
                        label="Jatuh Tempo Garansi"
                        type="date"
                        :error="form.errors.warranty_expiry"
                    />

                    <AppSelect
                        v-model="form.depreciation_method"
                        :options="depreciationMethods"
                        label="Metode Penyusutan"
                        :error="form.errors.depreciation_method"
                        required
                    />

                    <AppInput
                        v-model="form.useful_life_months"
                        label="Usia Ekonomis (Bulan)"
                        type="number"
                        min="1"
                        :error="form.errors.useful_life_months"
                        required
                    />

                    <AppInput
                        v-model="form.salvage_value"
                        label="Nilai Sisa"
                        :numberFormat="true"
                        :error="form.errors.salvage_value"
                        required
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan"
                    :error="form.errors.notes"
                />
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Aset</h3>
                <p class="mb-2">Aset adalah barang atau benda yang dimiliki oleh perusahaan. Contoh: Kendaraan, Mesin, Bangunan, dll.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan yang sesuai</li>
                    <li>Pilih cabang yang sesuai</li>
                    <li>Pilih kategori yang sesuai</li>
                    <li>Nama aset adalah nama dari aset yang digunakan untuk identifikasi.</li>
                    <li>Nomor seri aset adalah nomor yang digunakan untuk identifikasi aset secara unik.</li>
                    <li>Status aset adalah status dari aset yang digunakan untuk mengetahui apakah aset aktif, tidak aktif, atau dalam perbaikan.</li>
                    <li>Harga pembelian adalah total harga perolehan aset yang digunakan untuk menghitung penyusutan aset.</li>
                    <li>Tanggal pembelian adalah tanggal dimana aset dibeli.</li>
                    <li>Supplier adalah supplier dari aset.</li>
                    <li>Jatuh tempo garansi adalah tanggal dimana garansi aset berakhir.</li>
                    <li>Metode penyusutan adalah metode yang digunakan untuk menghitung penyusutan aset.</li>
                    <li>Usia ekonomis adalah jumlah bulan dimana aset dapat digunakan, dan digunakan untuk menghitung penyusutan aset.</li>
                    <li>Nilai sisa adalah nilai dari aset setelah digunakan, dan digunakan untuk menghitung penyusutan aset.</li>
                    <li>Catatan aset adalah deskripsi singkat yang menjelaskan aset.</li>
                </ul>
            </div>
        </div>

        <div class="flex items-center mt-4">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ asset ? 'Ubah' : 'Buat' }} Aset
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!asset"
                type="button"
                @click="submitForm(true)"
                class="mr-2"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton
                @click="$inertia.visit(route('assets.index', filters))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 