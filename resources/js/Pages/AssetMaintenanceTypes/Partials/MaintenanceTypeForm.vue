<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    maintenanceType: Object,
    categories: Array,
    companies: Array,
    filters: Object,
    accounts: {
        type: Array,
        required: true
    }
});

const form = useForm({
    name: props.maintenanceType?.name || '',
    asset_category_id: props.maintenanceType?.asset_category_id || '',
    description: props.maintenanceType?.description || '',
    maintenance_interval: props.maintenanceType?.maintenance_interval || '',
    maintenance_interval_days: props.maintenanceType?.maintenance_interval_days || '',
    maintenance_cost_account_id: props.maintenanceType?.maintenance_cost_account_id || '',
    company_ids: props.maintenanceType?.companies?.map(c => c.id) || props.companies.map(c => c.id),
    create_another: false,
});

// Maintenance interval presets
const intervalPresets = [
    { label: 'Pilih interval', value: '' },
    { label: '1 bulan', value: '1 bulan', days: 30 },
    { label: '3 bulan', value: '3 bulan', days: 90 },
    { label: '6 bulan', value: '6 bulan', days: 180 },
    { label: '1 tahun', value: '1 tahun', days: 365 },
    { label: '2 tahun', value: '2 tahun', days: 730 },
    { label: 'Custom', value: 'custom' },
];

// Filter accounts by type (for maintenance costs: beban_operasional, beban_lainnya, etc)
const expenseAccounts = computed(() => props.accounts.filter(account => 
    ['beban_pokok_penjualan', 'beban', 'beban_lainnya'].includes(account.type)
));

function applyIntervalPreset(intervalValue) {
    if (intervalValue === 'custom') {
        // Keep current values for custom interval
        return;
    }
    
    const preset = intervalPresets.find(p => p.value === intervalValue);
    if (preset && preset.days) {
        form.maintenance_interval_days = preset.days;
    }
}

// Watch for changes to the interval
watch(() => form.maintenance_interval, (newValue) => {
    applyIntervalPreset(newValue);
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.maintenanceType) {
        form.put(route('asset-maintenance-types.update', props.maintenanceType.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    } else {
        form.post(route('asset-maintenance-types.store'), {
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
                <div class="grid grid-cols-1 gap-4">
                    <AppSelect
                        v-model="form.asset_category_id"
                        :options="props.categories.map(category => ({ value: category.id, label: category.name }))"
                        label="Kategori Aset"
                        :error="form.errors.asset_category_id"
                        required
                    />

                    <AppInput
                        v-model="form.name"
                        label="Nama Tipe Pemeliharaan"
                        :error="form.errors.name"
                        required
                    />

                    <AppSelect
                        v-model="form.company_ids"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan"
                        :error="form.errors.company_ids"
                        multiple
                        required
                    />

                    <AppTextarea
                        v-model="form.description"
                        label="Deskripsi"
                        :error="form.errors.description"
                    />

                    <AppSelect
                        v-model="form.maintenance_cost_account_id"
                        :options="expenseAccounts.map(account => ({
                            value: account.id,
                            label: account.code + ' - ' + account.name
                        }))"
                        label="Akun Biaya Pemeliharaan"
                        hint="Akun yang digunakan untuk mencatat biaya pemeliharaan"
                        :error="form.errors.maintenance_cost_account_id"
                        required
                    />

                    <div class="border-t pt-4 mt-4">
                        <h3 class="text-lg font-medium mb-4">Pengaturan Interval Pemeliharaan</h3>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <AppSelect
                                v-model="form.maintenance_interval"
                                :options="intervalPresets"
                                label="Interval Pemeliharaan"
                                hint="Pilih interval pemeliharaan yang direkomendasikan"
                                :error="form.errors.maintenance_interval"
                            />

                            <AppInput
                                v-model="form.maintenance_interval_days"
                                label="Interval (Hari)"
                                type="number"
                                min="1"
                                max="3650"
                                hint="Jumlah hari antara pemeliharaan"
                                :error="form.errors.maintenance_interval_days"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Tipe Pemeliharaan</h3>
                <p class="mb-2">Tipe pemeliharaan menentukan jenis perawatan yang perlu dilakukan pada aset dalam kategori tertentu.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih kategori aset yang sesuai untuk tipe pemeliharaan ini</li>
                    <li>Tentukan interval pemeliharaan yang direkomendasikan</li>
                    <li>Tentukan akun biaya yang akan digunakan untuk pemeliharaan ini</li>
                    <li>Pilih perusahaan yang dapat menggunakan tipe pemeliharaan ini</li>
                </ul>
            </div>
        </div>

        <div class="flex items-center mt-4">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ maintenanceType ? 'Ubah' : 'Buat' }} Tipe Pemeliharaan
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!maintenanceType"
                type="button"
                @click="submitForm(true)"
                class="mr-2"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton
                @click="$inertia.visit(route('asset-maintenance-types.index', filters))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 