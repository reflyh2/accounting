<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AssetCreateModal from '@/Components/AssetCreateModal.vue';
import AlertNotification from '@/Components/AlertNotification.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';
import { ref, watch, onMounted, computed } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';
import { formatNumber } from '@/utils/numberFormat';
import axios from 'axios';

const page = usePage();

const props = defineProps({
    assetPurchase: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    currencies: Array,
    assets: Array,
    assetCategories: Array,
    filters: Object,
});

const form = useForm({
    company_id: props.assetPurchase?.branch?.branch_group?.company_id || null,
    branch_id: props.assetPurchase?.branch_id || null,
    partner_id: props.assetPurchase?.partner_id || null,
    currency_id: props.assetPurchase?.currency_id || page.props.primaryCurrency?.id || null,
    exchange_rate: props.assetPurchase?.exchange_rate || 1,
    invoice_date: props.assetPurchase?.invoice_date || new Date().toISOString().split('T')[0],
    due_date: props.assetPurchase?.due_date || new Date().toISOString().split('T')[0],
    notes: props.assetPurchase?.notes || '',
    status: props.assetPurchase?.status || 'open',
    details: props.assetPurchase?.asset_invoice_details?.map(detail => ({
        id: detail.id,
        asset_id: detail.asset_id,
        description: detail.description,
        quantity: detail.quantity,
        unit_price: detail.unit_price,
    })) || [
        { asset_id: null, description: '', quantity: 1, unit_price: 0 },
    ],
    create_another: false,
});

const partnerName = ref(props.assetPurchase?.partner?.name || '');
const partnerTableHeaders = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'actions', label: '' }
];

const submitted = ref(false);
const selectedCompany = ref(props.assetPurchase?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));
const showAssetModal = ref(false);
const currentDetailIndex = ref(null);
const priceWarnings = ref({});
const notification = ref({
    show: false,
    type: 'success',
    message: ''
});

const partnerUrl = computed(() => {
    return route('api.partners', { company_id: selectedCompany.value, roles: ['asset_supplier'] });
});

// Computed currency options
const currencyOptions = computed(() => {
    return props.currencies.map(currency => ({
        value: currency.id,
        label: `${currency.code} - ${currency.name}`
    }));
});

// Computed current currency symbol
const currentCurrencySymbol = computed(() => {
    const currency = props.currencies.find(c => c.id == form.currency_id);
    return currency?.symbol || page.props.primaryCurrency?.symbol || '';
});

// Computed primary currency amount
const primaryCurrencyAmount = computed(() => {
    if (form.currency_id == page.props.primaryCurrency?.id) {
        return totalAmount.value;
    }
    return totalAmount.value * (Number(form.exchange_rate) || 1);
});

// Computed available assets
const availableAssets = computed(() => props.assets.map(asset => ({
    id: asset.id,
    code: asset.code,
    name: asset.name,
    cost_basis: asset.cost_basis
})));

function updateExchangeRate() {
    if (!form.currency_id || !selectedCompany.value) {
        form.exchange_rate = 1;
        return;
    }
    
    const currency = props.currencies.find(c => c.id == form.currency_id);
    if (currency && currency.company_rates) {
        const companyRate = currency.company_rates.find(rate => rate.company_id == selectedCompany.value);
        if (companyRate) {
            form.exchange_rate = companyRate.exchange_rate;
        }
    }
}

// Watch currency selection to update exchange rate
watch(() => form.currency_id, () => {
    updateExchangeRate();

    // Trigger price change check for all details when currency changes
    form.details.forEach((_, index) => {
        onPriceChange(index);
    });
});

watch(selectedCompany, (newCompanyId) => {
    if (!props.assetPurchase) {
        form.currency_id = page.props.primaryCurrency?.id || null;
        form.exchange_rate = 1;
    }
    router.reload({ only: ['branches', 'currencies', 'partners'], data: { company_id: selectedCompany.value } });
}, { immediate: false });

watch(
    () => props.branches,
    (newBranches) => {
        if (!props.assetPurchase && newBranches && newBranches.length === 1) {
            form.branch_id = newBranches[0].id;
        }
    },
    { immediate: true, deep: true }
);

watch(() => form.branch_id, () => {
    router.reload({ only: ['assets'], data: { company_id: selectedCompany.value, branch_id: form.branch_id } });
}, { immediate: true });

onMounted(() => {
    const initialCompanyId = props.assetPurchase?.company_id || (props.companies.length === 1 ? props.companies[0].id : null);
    selectedCompany.value = initialCompanyId;
    form.company_id = initialCompanyId;
    
    // Trigger initial data load if company is pre-selected but branches not loaded
    if (initialCompanyId && (!props.branches || props.branches.length === 0)) {
        router.reload({ only: ['branches', 'assets'], data: { company_id: initialCompanyId } });
    }
    
    if (!props.assetPurchase && props.branches && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
});

function addDetail() {
    form.details.push({ asset_id: null, description: '', quantity: 1, unit_price: 0 });
}

function removeDetail(index) {
    form.details.splice(index, 1);
}

function openAssetModal(index = null) {
    currentDetailIndex.value = index;
    showAssetModal.value = true;
}

function closeAssetModal() {
    showAssetModal.value = false;
    currentDetailIndex.value = null;
}

function handleAssetCreated(newAsset) {
    availableAssets.value.push({
        id: newAsset.id,
        code: newAsset.code || newAsset.id,
        name: newAsset.name,
        cost_basis: newAsset.cost_basis
    });
    
    if (currentDetailIndex.value !== null && form.details[currentDetailIndex.value]) {
        form.details[currentDetailIndex.value].asset_id = newAsset.id;
        form.details[currentDetailIndex.value].unit_price = newAsset.cost_basis || 0;
    }
    
    closeAssetModal();
}

function handleModalNotification(notificationData) {
    showNotification(notificationData.type, notificationData.message);
}

function onAssetChange(index) {
    const detail = form.details[index];
    const selectedAsset = availableAssets.value.find(asset => asset.id == detail.asset_id);
    
    if (selectedAsset && selectedAsset.cost_basis) {
        detail.unit_price = selectedAsset.cost_basis;
        delete priceWarnings.value[index];
    }
}

function onPriceChange(index) {
    const detail = form.details[index];
    const selectedAsset = availableAssets.value.find(asset => asset.id == detail.asset_id);
    
    if (selectedAsset && selectedAsset.cost_basis && (detail.unit_price * form.exchange_rate) != selectedAsset.cost_basis) {
        priceWarnings.value[index] = {
            assetCostBasis: selectedAsset.cost_basis,
            currentPrice: detail.unit_price * form.exchange_rate,
            assetId: selectedAsset.id,
            assetName: selectedAsset.name
        };
    } else {
        delete priceWarnings.value[index];
    }
}

async function updateAssetCostBasis(index) {
    const warning = priceWarnings.value[index];
    if (!warning) return;
    
    try {
        await axios.patch(route('assets.update-cost-basis', warning.assetId), {
            cost_basis: warning.currentPrice
        });
        
        const assetIndex = availableAssets.value.findIndex(asset => asset.id == warning.assetId);
        if (assetIndex !== -1) {
            availableAssets.value[assetIndex].cost_basis = warning.currentPrice;
        }
        
        delete priceWarnings.value[index];
        
        showNotification('success', 'Nilai perolehan aset berhasil diperbarui!');
    } catch (error) {
        console.error('Error updating asset cost basis:', error);
        showNotification('error', 'Gagal memperbarui nilai perolehan aset');
    }
}

function showNotification(type, message) {
    notification.value = {
        show: true,
        type,
        message
    };
}

function hideNotification() {
    notification.value.show = false;
}

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
                    form.reset('partner_id', 'invoice_date', 'due_date', 'notes', 'details', 'status');
                    form.details = [{ asset_id: null, description: '', quantity: 1, unit_price: 0 }];
                    form.invoice_date = new Date().toISOString().split('T')[0];
                    form.due_date = new Date().toISOString().split('T')[0];
                    form.status = 'open';
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
                <AppPopoverSearch
                    v-model="form.partner_id"
                    label="Supplier:"
                    placeholder="Pilih Supplier"
                    :url="partnerUrl"
                    valueKey="id"
                    :displayKeys="['name']"
                    :tableHeaders="partnerTableHeaders"
                    :initialDisplayValue="partnerName"
                    :error="form.errors.partner_id"
                    :modalTitle="'Pilih Supplier Aset'"
                    :disabled="!selectedCompany"
                    required
                />
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.currency_id"
                        :options="currencyOptions"
                        label="Mata Uang:"
                        placeholder="Pilih Mata Uang"
                        :error="form.errors.currency_id"
                        required
                    />
                    
                    <AppInput
                        v-model="form.exchange_rate"
                        :numberFormat="true"
                        label="Nilai Tukar:"
                        :error="form.errors.exchange_rate"
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

        <div class="overflow-x-auto mt-6">
            <h3 class="text-lg font-semibold mb-2">Detail Pembelian Aset</h3>
             <p v-if="form.errors.details" class="text-sm text-red-600 mb-2">{{ form.errors.details }}</p>
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-48 px-1.5 py-1.5">Aset</th>
                        <th class="border border-gray-300 text-sm min-w-60 px-1.5 py-1.5">Deskripsi</th>
                        <th class="border border-gray-300 text-sm min-w-24 px-1.5 py-1.5">Qty</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Harga Satuan</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Total</th>
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
                                @update:modelValue="onAssetChange(index)"
                            >
                                <template #suffix>
                                    <button
                                        type="button"
                                        @click.stop="openAssetModal(index)"
                                        class="text-main-600 hover:text-main-800 rounded hover:bg-main-50 transition-colors"
                                        title="Tambah Aset Baru"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </button>
                                </template>
                            </AppSelect>
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="detail.description"
                                :error="form.errors[`details.${index}.description`]?.[0]"
                                placeholder="Deskripsi (opsional)"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="detail.quantity"
                                :numberFormat="true"
                                :error="form.errors[`details.${index}.quantity`]?.[0]"
                                required
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                            />
                        </td>
                         <td class="border border-gray-300 px-1.5 py-1.5">
                            <AppInput
                                v-model="detail.unit_price"
                                :numberFormat="true"
                                :error="form.errors[`details.${index}.unit_price`]?.[0]"
                                required
                                :prefix="currentCurrencySymbol" 
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                @update:modelValue="onPriceChange(index)"
                            />
                            <div v-if="priceWarnings[index]" class="mt-1 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <p class="text-yellow-800 font-medium">⚠️ Perbedaan Harga</p>
                                        <p class="text-yellow-700 mt-1">
                                            Nilai perolehan aset: {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(priceWarnings[index].assetCostBasis) }}<br>
                                            Harga saat ini: {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(priceWarnings[index].currentPrice) }}
                                        </p>
                                        <button 
                                            type="button"
                                            @click="updateAssetCostBasis(index)"
                                            class="mt-2 px-2 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded text-xs border border-yellow-300"
                                        >
                                            Perbarui Nilai Perolehan Aset
                                        </button>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="delete priceWarnings[index]"
                                        class="text-yellow-600 hover:text-yellow-800 ml-2"
                                    >
                                        ×
                                    </button>
                                </div>
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 font-bold">
                            {{ currentCurrencySymbol }} {{ formatNumber(detail.quantity * detail.unit_price) }}
                            <div v-if="form.currency_id != null && form.currency_id != page.props.primaryCurrency?.id" class="text-gray-500 text-xs font-normal">
                                = {{ page.props.primaryCurrency?.symbol }} {{ formatNumber((detail.quantity * detail.unit_price) * form.exchange_rate) }}
                            </div>
                        </td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-center align-middle">
                            <button type="button" @click="removeDetail(index)" class="text-red-500 hover:text-red-700">
                                <TrashIcon class="w-5 h-5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="border border-gray-300 px-1.5 py-1.5 text-right font-semibold">Total Faktur</td>
                        <td class="border border-gray-300 px-1.5 py-1.5 font-bold">
                            {{ currentCurrencySymbol }}{{ formatNumber(totalAmount) }}
                            <div v-if="form.currency_id != null && form.currency_id != page.props.primaryCurrency?.id" class="text-gray-500 text-xs font-normal">
                                = {{ page.props.primaryCurrency?.symbol }} {{ formatNumber(primaryCurrencyAmount) }}
                            </div>
                        </td>
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
    
    <AssetCreateModal
        :show="showAssetModal"
        :company-id="selectedCompany"
        :companies="props.companies"
        :categories="props.assetCategories"
        @close="closeAssetModal"
        @asset-created="handleAssetCreated"
        @notification="handleModalNotification"
    />
    
    <AlertNotification
        v-if="notification.show"
        :type="notification.type"
        :message="notification.message"
        @close="hideNotification"
    />
</template> 