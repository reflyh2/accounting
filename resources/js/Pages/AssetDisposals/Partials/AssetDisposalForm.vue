<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    assetDisposal: Object,
    companies: Array,
    branches: Array,
    assets: Array,
    statusOptions: Object,
    disposalTypeOptions: Object,
    filters: Object,
});

const form = useForm({
    company_id: props.assetDisposal?.branch?.branch_group?.company_id || null,
    branch_id: props.assetDisposal?.branch_id || null,
    disposal_date: props.assetDisposal?.disposal_date || new Date().toISOString().split('T')[0],
    disposal_type: props.assetDisposal?.disposal_type || 'scrap',
    proceeds_amount: props.assetDisposal?.proceeds_amount || 0,
    notes: props.assetDisposal?.notes || '',
    details: props.assetDisposal?.asset_disposal_details?.map(detail => ({
        id: detail.id,
        asset_id: detail.asset_id,
        carrying_amount: detail.carrying_amount,
        proceeds_amount: detail.proceeds_amount,
        notes: detail.notes,
    })) || [
        { asset_id: null, carrying_amount: 0, proceeds_amount: 0, notes: '' },
    ],
    create_another: false,
});

const submitted = ref(false);
const selectedCompany = ref(props.assetDisposal?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));
const availableAssets = computed(() => props.assets ? props.assets.map(asset => ({
    id: asset.id,
    code: asset.code,
    name: asset.name,
    carrying_amount: asset.net_book_value,
})) : []);

watch(selectedCompany, (newCompanyId) => {
    router.reload({ only: ['branches'], data: { company_id: newCompanyId } });
}, { immediate: true });

watch(() => form.branch_id, (newBranchId) => {
    router.reload({ only: ['assets'], data: { company_id: selectedCompany.value, branch_id: newBranchId } });
}, { immediate: true });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.assetDisposal && newBranches && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true, deep: true }
);

onMounted(() => {
    if (!props.assetDisposal && props.branches && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
});

function addDetail() {
    form.details.push({ asset_id: null, carrying_amount: 0, proceeds_amount: 0, notes: '' });
}

function removeDetail(index) {
    form.details.splice(index, 1);
}

function onAssetChange(index) {
    const detail = form.details[index];
    const selectedAsset = availableAssets.value.find(asset => asset.id == detail.asset_id);
    
    if (selectedAsset) {
        detail.carrying_amount = selectedAsset.carrying_amount;
    }
}

const totalProceeds = computed(() => {
    return form.details.reduce((sum, detail) => {
        return sum + (Number(detail.proceeds_amount));
    }, 0);
});

watch(totalProceeds, (newTotal) => {
    form.proceeds_amount = newTotal;
});

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    if (props.assetDisposal) {
        form.put(route('asset-disposals.update', props.assetDisposal.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.post(route('asset-disposals.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset('notes', 'details');
                    form.details = [{ asset_id: null, carrying_amount: 0, proceeds_amount: 0, notes: '' }];
                    form.clearErrors();
                }
            },
            onError: () => { submitted.value = false; }
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
                        :disabled="!!props.assetDisposal" 
                        required
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches?.map(branch => ({ value: branch.id, label: branch.name })) || []"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.assetDisposal"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.disposal_date"
                        type="date"
                        label="Tanggal Pelepasan:"
                        :error="form.errors.disposal_date"
                        required
                    />
                    <AppSelect
                        v-model="form.disposal_type"
                        :options="Object.entries(props.disposalTypeOptions).map(([value, label]) => ({ value, label }))"
                        label="Jenis Pelepasan:"
                        :error="form.errors.disposal_type"
                        required
                    />
                </div>
                 <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                 />
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Pelepasan Aset</h3>
                <p class="mb-2">Catat detail pelepasan aset. Pastikan semua informasi akurat.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih Perusahaan dan Cabang terkait.</li>
                    <li>Pilih jenis pelepasan dan tanggalnya.</li>
                    <li>Isi detail aset yang dilepaskan pada tabel di bawah.</li>
                    <li>Total hasil akan dihitung otomatis dari detail.</li>
                </ul>
                <div class="mt-4 p-2 bg-white border rounded">
                    <p class="font-semibold">Total Hasil:</p>
                    <p class="text-xl font-bold">{{ formatNumber(form.proceeds_amount) }}</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto mt-6">
            <h3 class="text-lg font-semibold mb-2">Detail Aset Dilepaskan</h3>
             <p v-if="form.errors.details" class="text-sm text-red-600 mb-2">{{ form.errors.details }}</p>
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Aset</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Nilai Tercatat</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Hasil</th>
                        <th class="border border-gray-300 text-sm min-w-60 px-1.5 py-1.5">Catatan</th>
                        <th class="border border-gray-300 px-1.5 py-1.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(detail, index) in form.details" :key="index">
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppSelect
                                v-model="detail.asset_id"
                                :options="availableAssets.map(asset => ({ value: asset.id, label: asset.code + ' - ' + asset.name }))"
                                :error="form.errors[`details.${index}.asset_id`]"
                                placeholder="Pilih Aset"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                @update:modelValue="onAssetChange(index)"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                           <AppInput
                                v-model="detail.carrying_amount"
                                :numberFormat="true"
                                :error="form.errors[`details.${index}.carrying_amount`]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                :disabled="true"
                            />
                        </td>
                         <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="detail.proceeds_amount"
                                :numberFormat="true"
                                :error="form.errors[`details.${index}.proceeds_amount`]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="detail.notes"
                                :error="form.errors[`details.${index}.notes`]"
                                placeholder="Catatan (opsional)"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                            <button type="button" @click="removeDetail(index)" class="text-red-500 hover:text-red-700 mb-4">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">Total Hasil</td>
                        <td class="border border-gray-300 px-1.5 py-1.5 font-bold">
                           {{ formatNumber(totalProceeds) }}
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
            <div class="flex mt-2 mb-4">
                <button type="button" @click="addDetail" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Baris Detail
                </button>
            </div>
        </div>

        <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" :disabled="form.processing || submitted" class="mr-2">
                {{ props.assetDisposal ? 'Ubah' : 'Simpan' }} Dokumen
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.assetDisposal" type="button" @click="submitForm(true)" :disabled="form.processing || submitted" class="mr-2">
                Simpan & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('asset-disposals.index', filters))" :disabled="form.processing || submitted">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 