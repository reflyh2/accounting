<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppCheckbox from '@/Components/AppCheckbox.vue';

const props = defineProps({
    costPool: Object,
    companies: Array,
    branches: Array,
    assets: Array,
    poolTypeOptions: Object,
    allocationRuleOptions: Object,
    filters: Object,
    isEdit: {
        type: Boolean,
        default: false,
    },
});

const submitted = ref(false);

const form = useForm({
    company_id: props.costPool?.company_id || (props.companies?.length === 1 ? props.companies[0].id : null),
    code: props.costPool?.code || '',
    name: props.costPool?.name || '',
    pool_type: props.costPool?.pool_type || 'asset',
    allocation_rule: props.costPool?.allocation_rule || 'revenue_based',
    asset_id: props.costPool?.asset_id || null,
    branch_id: props.costPool?.branch_id || null,
    is_active: props.costPool?.is_active ?? true,
    notes: props.costPool?.notes || '',
    create_another: false,
});

const poolTypeOptionsList = computed(() =>
    Object.entries(props.poolTypeOptions || {}).map(([value, label]) => ({
        value,
        label,
    }))
);

const allocationRuleOptionsList = computed(() =>
    Object.entries(props.allocationRuleOptions || {}).map(([value, label]) => ({
        value,
        label,
    }))
);

const filteredBranches = computed(() => {
    if (!form.company_id) return [];
    return props.branches.filter(b => b.company_id === form.company_id);
});

const filteredAssets = computed(() => {
    if (!form.company_id) return [];
    return props.assets.filter(a => a.company_id === form.company_id);
});

// Reset linked entity when pool type changes
watch(() => form.pool_type, (newType) => {
    if (newType !== 'asset') form.asset_id = null;
    if (newType !== 'branch') form.branch_id = null;
});

// Reset linked entity when company changes
watch(() => form.company_id, () => {
    form.asset_id = null;
    form.branch_id = null;
});

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;

    if (props.isEdit) {
        form.put(route('costing.cost-pools.update', props.costPool.id), {
            preserveScroll: true,
            onSuccess: () => submitted.value = false,
            onError: () => submitted.value = false,
        });
    } else {
        form.post(route('costing.cost-pools.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            },
            onError: () => submitted.value = false,
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
                        v-model="form.company_id"
                        :options="props.companies.map(c => ({ value: c.id, label: c.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :error="form.errors.company_id"
                        :disabled="!!props.costPool"
                        required
                    />

                    <AppSelect
                        v-model="form.pool_type"
                        :options="poolTypeOptionsList"
                        label="Tipe Pool:"
                        placeholder="Pilih Tipe"
                        :error="form.errors.pool_type"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.code"
                        label="Kode Pool:"
                        placeholder="Contoh: POOL-001"
                        :error="form.errors.code"
                        required
                    />

                    <AppInput
                        v-model="form.name"
                        label="Nama Pool:"
                        placeholder="Nama pool biaya"
                        :error="form.errors.name"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.allocation_rule"
                        :options="allocationRuleOptionsList"
                        label="Aturan Alokasi Default:"
                        placeholder="Pilih Aturan"
                        :error="form.errors.allocation_rule"
                    />

                    <AppSelect
                        v-if="form.pool_type === 'asset'"
                        v-model="form.asset_id"
                        :options="filteredAssets.map(a => ({ value: a.id, label: a.label }))"
                        label="Aset Terkait:"
                        placeholder="Pilih Aset"
                        :error="form.errors.asset_id"
                        :disabled="!form.company_id"
                    />

                    <AppSelect
                        v-if="form.pool_type === 'branch'"
                        v-model="form.branch_id"
                        :options="filteredBranches.map(b => ({ value: b.id, label: b.name }))"
                        label="Cabang Terkait:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!form.company_id"
                    />
                </div>

                <div class="mt-4">
                    <AppCheckbox
                        v-model="form.is_active"
                        label="Pool Aktif"
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                    :rows="3"
                />
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Pool Biaya</h3>
                <p class="mb-2">Pool Biaya digunakan untuk mengakumulasi biaya tidak langsung sebelum dialokasikan ke faktur penjualan.</p>
                <ul class="list-disc list-inside">
                    <li><strong>Aset</strong> - Biaya terkait aset (depresiasi)</li>
                    <li><strong>Layanan</strong> - Biaya layanan bersama</li>
                    <li><strong>Cabang</strong> - Overhead cabang</li>
                </ul>
            </div>
        </div>

        <div class="mt-4 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2" :disabled="submitted">
                {{ props.isEdit ? 'Ubah' : 'Tambah' }} Pool Biaya
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.isEdit" type="button" @click="submitForm(true)" class="mr-2" :disabled="submitted">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('costing.cost-pools.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>
