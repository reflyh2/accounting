<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    assetTransfer: Object,
    companies: Array,
    fromBranches: Array,
    toBranches: Array,
    assets: Array,
    filters: Object,
});

const form = useForm({
    from_company_id: props.assetTransfer?.from_company_id || null,
    from_branch_id: props.assetTransfer?.from_branch_id || null,
    to_company_id: props.assetTransfer?.to_company_id || null,
    to_branch_id: props.assetTransfer?.to_branch_id || null,
    transfer_date: props.assetTransfer?.transfer_date || new Date().toISOString().split('T')[0],
    notes: props.assetTransfer?.notes || '',
    details: props.assetTransfer?.asset_transfer_details?.map(detail => ({
        id: detail.id,
        asset_id: detail.asset_id,
        notes: detail.notes,
    })) || [
        { asset_id: null, notes: '' },
    ],
    create_another: false,
});

const submitted = ref(false);

const fromCompanyId = ref(props.assetTransfer?.from_company_id || null);
const fromBranchId = ref(props.assetTransfer?.from_branch_id || null);
const toCompanyId = ref(props.assetTransfer?.to_company_id || null);

const availableAssets = computed(() => props.assets || []);

watch(fromCompanyId, (newCompanyId) => {
    form.from_company_id = newCompanyId;
    form.from_branch_id = null; // Reset branch
    fromBranchId.value = null;
    router.reload({ only: ['fromBranches'], data: { from_company_id: newCompanyId }});
});

watch(fromBranchId, (newBranchId) => {
    form.from_branch_id = newBranchId;
    router.reload({ only: ['assets'], data: { from_branch_id: newBranchId }});
});

watch(toCompanyId, (newCompanyId) => {
    form.to_company_id = newCompanyId;
    form.to_branch_id = null; // Reset branch
    router.reload({ only: ['toBranches'], data: { to_company_id: newCompanyId }});
});

onMounted(() => {
    if (props.assetTransfer) {
        fromCompanyId.value = props.assetTransfer.from_company_id;
        fromBranchId.value = props.assetTransfer.from_branch_id;
        toCompanyId.value = props.assetTransfer.to_company_id;
    }
});

function addDetail() {
    form.details.push({ asset_id: null, notes: '' });
}

function removeDetail(index) {
    form.details.splice(index, 1);
}

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    if (props.assetTransfer) {
        form.put(route('asset-transfers.update', props.assetTransfer.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.post(route('asset-transfers.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.details = [{ asset_id: null, notes: '' }];
                    form.transfer_date = new Date().toISOString().split('T')[0];
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
                    <AppInput
                        v-model="form.transfer_date"
                        type="date"
                        label="Tanggal Transfer:"
                        :error="form.errors.transfer_date"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="fromCompanyId"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Dari Perusahaan:"
                        placeholder="Pilih Perusahaan Asal"
                        :error="form.errors.from_company_id"
                        :disabled="!!props.assetTransfer" 
                        required
                    />
                    <AppSelect
                        v-model="fromBranchId"
                        :options="props.fromBranches?.map(branch => ({ value: branch.id, label: branch.name })) || []"
                        label="Dari Cabang:"
                        placeholder="Pilih Cabang Asal"
                        :error="form.errors.from_branch_id"
                        :disabled="!!props.assetTransfer || !fromCompanyId"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="toCompanyId"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Ke Perusahaan:"
                        placeholder="Pilih Perusahaan Tujuan"
                        :error="form.errors.to_company_id"
                        required
                    />
                    <AppSelect
                        v-model="form.to_branch_id"
                        :options="props.toBranches?.map(branch => ({ value: branch.id, label: branch.name })) || []"
                        label="Ke Cabang:"
                        placeholder="Pilih Cabang Tujuan"
                        :error="form.errors.to_branch_id"
                        :disabled="!toCompanyId"
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
                <h3 class="text-lg font-semibold mb-2">Informasi Transfer Aset</h3>
                <p class="mb-2">Gunakan formulir ini untuk mencatat perpindahan aset antar cabang atau perusahaan.</p>
                <ul class="list-disc list-inside">
                    <li>Tentukan tanggal transfer.</li>
                    <li>Pilih perusahaan dan cabang asal.</li>
                    <li>Pilih perusahaan dan cabang tujuan.</li>
                    <li>Aset yang tersedia untuk ditransfer akan muncul setelah cabang asal dipilih.</li>
                    <li>Tambahkan aset yang akan ditransfer pada tabel di bawah.</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto mt-6">
            <h3 class="text-lg font-semibold mb-2">Detail Aset yang Ditransfer</h3>
             <p v-if="form.errors.details" class="text-sm text-red-600 mb-2">{{ form.errors.details }}</p>
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Aset</th>
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
                                :error="form.errors[`details.${index}.asset_id`]?.[0]"
                                placeholder="Pilih Aset"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="detail.notes"
                                :error="form.errors[`details.${index}.notes`]?.[0]"
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
            </table>
            <div class="flex mt-2 mb-4">
                <button type="button" @click="addDetail" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Baris Detail
                </button>
            </div>
        </div>

        <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" :disabled="form.processing || submitted" class="mr-2">
                {{ props.assetTransfer ? 'Ubah' : 'Simpan' }} Transfer
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('asset-transfers.index', filters))" :disabled="form.processing || submitted">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 