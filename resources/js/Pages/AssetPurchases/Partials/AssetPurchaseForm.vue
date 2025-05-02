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
    assetPurchase: Object, // Renamed from journal
    companies: Array,
    branches: Array,
    partners: Array, // Added partners
    assets: Array,   // Added assets
    filters: Object,
    // primaryCurrency: Object, // Removed - currency handling might differ or be implicit
});

const form = useForm({
    company_id: props.assetPurchase?.branch?.branch_group?.company_id || null,
    branch_id: props.assetPurchase?.branch_id || null,
    partner_id: props.assetPurchase?.partner_id || null, // Added partner_id
    invoice_date: props.assetPurchase?.invoice_date || new Date().toISOString().split('T')[0],
    due_date: props.assetPurchase?.due_date || new Date().toISOString().split('T')[0],
    notes: props.assetPurchase?.notes || '',
    status: props.assetPurchase?.status || 'open', // Default status
    details: props.assetPurchase?.asset_invoice_details?.map(detail => ({ // Renamed from journal_entries
        id: detail.id, // Keep track of existing detail IDs for updates
        asset_id: detail.asset_id,
        description: detail.description,
        quantity: detail.quantity,
        unit_price: detail.unit_price,
    })) || [
        { asset_id: null, description: '', quantity: 1, unit_price: 0 }, // Default first detail line
    ],
    create_another: false,
});

const submitted = ref(false);
const selectedCompany = ref(props.assetPurchase?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));

// Status options
const statusOptions = [
    { value: 'open', label: 'Open' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'voided', label: 'Voided' },
    { value: 'closed', label: 'Closed' },
    { value: 'partially_paid', label: 'Partially Paid' },
];

// Watch company selection to reload branches (only on create)
watch(selectedCompany, (newCompanyId) => {
    router.reload({ only: ['branches'], data: { company_id: newCompanyId } });
}, { immediate: true });

// Watch branches prop to auto-select if only one exists (only on create)
watch(
    () => props.branches,
    (newBranches) => {
        if (!props.assetPurchase && newBranches && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true, deep: true } // Use deep watch if branches structure is complex
);

// Initialize company and branch on mount
onMounted(() => {
    selectedCompany.value = props.assetPurchase?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
    if (!props.assetPurchase && props.branches && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
    // Set default partner if only one exists?
    // if (!props.assetPurchase && props.partners && props.partners.length === 1) {
    //     form.partner_id = props.partners[0].id;
    // }
});

function addDetail() {
    form.details.push({ asset_id: null, description: '', quantity: 1, unit_price: 0 });
}

function removeDetail(index) {
    form.details.splice(index, 1);
}

// Computed property for total amount
const totalAmount = computed(() => {
    return form.details.reduce((sum, detail) => {
        return sum + (Number(detail.quantity) * Number(detail.unit_price));
    }, 0);
});

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    if (props.assetPurchase) {
        form.put(route('asset-purchases.update', props.assetPurchase.id), {
            preserveScroll: true,
            onSuccess: () => { submitted.value = false; },
            onError: () => { submitted.value = false; }
        });
    } else {
        form.post(route('asset-purchases.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    // Reset form fields for next entry
                    form.reset('partner_id', 'invoice_date', 'due_date', 'notes', 'details', 'status');
                    form.details = [{ asset_id: null, description: '', quantity: 1, unit_price: 0 }];
                    form.invoice_date = new Date().toISOString().split('T')[0];
                    form.due_date = new Date().toISOString().split('T')[0];
                    form.status = 'open';
                    // Keep company/branch if desired, or reset them too
                    // form.company_id = null; // Or keep previous
                    // form.branch_id = null; // Or keep previous
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
                <!-- Header Fields -->
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :error="form.errors.company_id"
                        :disabled="!!props.assetPurchase" 
                        required
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches?.map(branch => ({ value: branch.id, label: branch.name })) || []"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.assetPurchase"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                     <AppSelect
                        v-model="form.partner_id"
                        :options="props.partners.map(partner => ({ value: partner.id, label: partner.name }))"
                        label="Partner (Vendor):"
                        placeholder="Pilih Partner"
                        :error="form.errors.partner_id"
                        required
                    />
                     <AppSelect
                        v-model="form.status"
                        :options="statusOptions"
                        label="Status Faktur:"
                        :error="form.errors.status"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.invoice_date"
                        type="date"
                        label="Tanggal Faktur:"
                        :error="form.errors.invoice_date"
                        required
                    />
                    <AppInput
                        v-model="form.due_date"
                        type="date"
                        label="Tanggal Jatuh Tempo:"
                        :error="form.errors.due_date"
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
                <h3 class="text-lg font-semibold mb-2">Informasi Pembelian Aset</h3>
                <p class="mb-2">Catat detail pembelian aset dari vendor. Pastikan semua informasi akurat.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih Perusahaan dan Cabang terkait.</li>
                    <li>Pilih Partner (Vendor) yang menerbitkan faktur.</li>
                    <li>Masukkan tanggal faktur dan tanggal jatuh tempo pembayaran.</li>
                    <li>Pilih status faktur yang sesuai.</li>
                    <li>Tambahkan catatan jika diperlukan.</li>
                    <li>Isi detail aset yang dibeli pada tabel di bawah.</li>
                </ul>
            </div>
        </div>

        <!-- Detail Lines Table -->
        <div class="overflow-x-auto mt-6">
            <h3 class="text-lg font-semibold mb-2">Detail Pembelian Aset</h3>
             <p v-if="form.errors.details" class="text-sm text-red-600 mb-2">{{ form.errors.details }}</p>
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 px-4 py-2">Aset</th>
                        <th class="border border-gray-300 text-sm min-w-60 px-4 py-2">Deskripsi</th>
                        <th class="border border-gray-300 text-sm min-w-24 px-4 py-2">Qty</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-4 py-2">Harga Satuan</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-4 py-2">Total</th>
                        <th class="border border-gray-300 px-4 py-2"></th> <!-- Action column -->
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(detail, index) in form.details" :key="index">
                        <td class="border border-gray-300 px-4 pt-4">
                            <AppSelect
                                v-model="detail.asset_id"
                                :options="props.assets.map(asset => ({ value: asset.id, label: asset.code + ' - ' + asset.name }))"
                                :error="form.errors[`details.${index}.asset_id`]?.[0]"
                                placeholder="Pilih Aset"
                                required
                            />
                        </td>
                        <td class="border border-gray-300 px-4 pt-4">
                            <AppInput
                                v-model="detail.description"
                                :error="form.errors[`details.${index}.description`]?.[0]"
                                placeholder="Deskripsi (opsional)"
                            />
                        </td>
                        <td class="border border-gray-300 px-4 pt-4">
                            <AppInput
                                v-model="detail.quantity"
                                :numberFormat="true"
                                :error="form.errors[`details.${index}.quantity`]?.[0]"
                                required
                            />
                        </td>
                         <td class="border border-gray-300 px-4 pt-4">
                            <AppInput
                                v-model="detail.unit_price"
                                :numberFormat="true"
                                :error="form.errors[`details.${index}.unit_price`]?.[0]"
                                required
                                prefix="Rp" 
                            />
                        </td>
                         <td class="border border-gray-300 px-4 pt-4 text-right align-middle">
                            <span class="mb-4 inline-block">Rp {{ formatNumber(Number(detail.quantity) * Number(detail.unit_price)) }}</span>
                        </td>
                        <td class="border border-gray-300 px-4 pt-4 text-center align-middle">
                            <button type="button" @click="removeDetail(index)" class="text-red-500 hover:text-red-700 mb-4">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="border border-gray-300 px-4 py-2 text-right font-semibold">Total Faktur</td>
                        <td class="border border-gray-300 px-4 py-2 text-right font-semibold">Rp {{ formatNumber(totalAmount) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <div class="flex mt-2 mb-4">
                <button type="button" @click="addDetail" class="flex items-center text-main-500 hover:text-main-700">
                    <PlusCircleIcon class="w-6 h-6 mr-2" /> Tambah Baris Detail
                </button>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" :disabled="form.processing || submitted" class="mr-2">
                {{ props.assetPurchase ? 'Ubah' : 'Simpan' }} Faktur
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.assetPurchase" type="button" @click="submitForm(true)" :disabled="form.processing || submitted" class="mr-2">
                Simpan & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('asset-purchases.index', filters))" :disabled="form.processing || submitted">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 