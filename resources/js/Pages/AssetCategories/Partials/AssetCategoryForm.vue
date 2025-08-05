<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import { ref, computed, watch } from 'vue';
import { PlusCircleIcon, TrashIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    assetCategory: Object,
    companies: Array,
    accounts: Array,
    filters: Object,
});

// Create a map of company accounts for easy access
const companyAccountsMap = computed(() => {
    const map = {};
    if (props.assetCategory && props.assetCategory.companies) {
        props.assetCategory.companies.forEach(company => {
            map[company.id] = {
                asset_account_id: company.pivot.asset_account_id || null,
                asset_depreciation_account_id: company.pivot.asset_depreciation_account_id || null,
                asset_accumulated_depreciation_account_id: company.pivot.asset_accumulated_depreciation_account_id || null,
                asset_amortization_account_id: company.pivot.asset_amortization_account_id || null,
                asset_prepaid_amortization_account_id: company.pivot.asset_prepaid_amortization_account_id || null,
                asset_rental_cost_account_id: company.pivot.asset_rental_cost_account_id || null,
                asset_acquisition_payable_account_id: company.pivot.asset_acquisition_payable_account_id || null,
                asset_sale_receivable_account_id: company.pivot.asset_sale_receivable_account_id || null,
                asset_financing_payable_account_id: company.pivot.asset_financing_payable_account_id || null,
            };
        });
    }
    return map;
});

// Get the initial selected company IDs from the asset category
const getInitialSelectedCompanies = () => {
    if (props.assetCategory && props.assetCategory.companies) {
        return props.assetCategory.companies.map(company => company.id);
    }
    return [];
};

const form = useForm({
    name: props.assetCategory?.name || '',
    code: props.assetCategory?.code || '',
    description: props.assetCategory?.description || '',
    selected_companies: getInitialSelectedCompanies(),
    companies: props.assetCategory ? [] : [],
    create_another: false,
});

// Initialize the companies array when the component is mounted
if (props.assetCategory) {
    form.companies = props.assetCategory.companies.map(company => ({
        id: company.id,
        asset_account_id: company.pivot.asset_account_id || null,
        asset_depreciation_account_id: company.pivot.asset_depreciation_account_id || null,
        asset_accumulated_depreciation_account_id: company.pivot.asset_accumulated_depreciation_account_id || null,
        asset_amortization_account_id: company.pivot.asset_amortization_account_id || null,
        asset_prepaid_amortization_account_id: company.pivot.asset_prepaid_amortization_account_id || null,
        asset_rental_cost_account_id: company.pivot.asset_rental_cost_account_id || null,
        asset_acquisition_payable_account_id: company.pivot.asset_acquisition_payable_account_id || null,
        asset_sale_receivable_account_id: company.pivot.asset_sale_receivable_account_id || null,
        asset_financing_payable_account_id: company.pivot.asset_financing_payable_account_id || null,
        asset_sale_profit_account_id: company.pivot.asset_sale_profit_account_id || null,
        asset_sale_loss_account_id: company.pivot.asset_sale_loss_account_id || null,
    }));
}

const submitted = ref(false);

// Watch for changes in the selected companies and update the companies array
watch(() => form.selected_companies, (newSelectedCompanies) => {
    // Add new companies
    newSelectedCompanies.forEach(companyId => {
        if (!form.companies.some(company => company.id === companyId)) {
            form.companies.push({
                id: companyId,
                asset_account_id: companyAccountsMap.value[companyId]?.asset_account_id || null,
                asset_depreciation_account_id: companyAccountsMap.value[companyId]?.asset_depreciation_account_id || null,
                asset_accumulated_depreciation_account_id: companyAccountsMap.value[companyId]?.asset_accumulated_depreciation_account_id || null,
                asset_amortization_account_id: companyAccountsMap.value[companyId]?.asset_amortization_account_id || null,
                asset_prepaid_amortization_account_id: companyAccountsMap.value[companyId]?.asset_prepaid_amortization_account_id || null,
                asset_rental_cost_account_id: companyAccountsMap.value[companyId]?.asset_rental_cost_account_id || null,
                asset_acquisition_payable_account_id: companyAccountsMap.value[companyId]?.asset_acquisition_payable_account_id || null,
                asset_sale_receivable_account_id: companyAccountsMap.value[companyId]?.asset_sale_receivable_account_id || null,
                asset_financing_payable_account_id: companyAccountsMap.value[companyId]?.asset_financing_payable_account_id || null,
                asset_sale_profit_account_id: companyAccountsMap.value[companyId]?.asset_sale_profit_account_id || null,
                asset_sale_loss_account_id: companyAccountsMap.value[companyId]?.asset_sale_loss_account_id || null,
            });
        }
    });

    // Remove companies that are no longer selected
    form.companies = form.companies.filter(company => 
        newSelectedCompanies.includes(company.id)
    );
}, { deep: true });

function submitForm(createAnother = false) {
    submitted.value = true;
    form.create_another = createAnother;
    
    if (props.assetCategory) {
        form.put(route('asset-categories.update', props.assetCategory.id), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
            },
            onError: () => {
                submitted.value = false;
            }
        });
    } else {
        form.post(route('asset-categories.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                    form.selected_companies = [];
                    form.companies = [];
                }
            },
            onError: () => {
                submitted.value = false;
            }
        });
    }
}

// Filter accounts to show only asset-related accounts
const assetAccounts = computed(() => {
    return props.accounts.filter(account => account.account_type === 'asset' || account.account_type === 'asset_depreciation' || account.account_type === 'expense');
});

// Get company-specific accounts for a company
const getCompanyAccounts = (companyId, accountType) => {
    return props.accounts.filter(account => 
        account.companies.some(c => c.id === companyId) && 
        (account.type === accountType)
    );
};

// Get company name by ID
const getCompanyName = (companyId) => {
    const company = props.companies.find(c => c.id === companyId);
    return company ? company.name : '';
};
</script>

<template>
    <form @submit.prevent="submitForm(false)" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <AppInput
                v-model="form.name"
                label="Nama:"
                :error="form.errors.name"
                required
            />
            
            <AppInput
                v-model="form.code"
                label="Kode:"
                :error="form.errors.code"
                required
            />
        </div>
        
        <AppTextarea
            v-model="form.description"
            label="Deskripsi:"
            :error="form.errors.description"
        />

        <div class="mt-6">
            <AppSelect
                v-model="form.selected_companies"
                :options="props.companies.map(c => ({ value: c.id, label: c.name }))"
                label="Perusahaan:"
                placeholder="Pilih Perusahaan"
                :error="form.errors.selected_companies"
                multiple
                required
            />
            <p class="text-sm text-gray-600 mt-2 mb-4">Pilih perusahaan yang akan menggunakan kategori aset ini. Anda dapat mengatur akun-akun yang digunakan untuk setiap perusahaan.</p>

            <div v-if="form.selected_companies.length === 0" class="p-4 rounded-md border border-gray-200 text-gray-500 text-center mt-4">
                Silakan pilih perusahaan terlebih dahulu untuk mengatur akun-akun terkait.
            </div>

            <div v-for="companyId in form.selected_companies" :key="companyId" class="mb-6 p-4 border border-gray-200 rounded-lg mt-4">
                <h4 class="font-medium text-lg mb-4">{{ getCompanyName(companyId) }}</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_account_id"
                        :options="getCompanyAccounts(companyId, 'aset_tetap').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Aset:"
                        placeholder="Pilih Akun Aset"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_account_id`]"
                    />
                    
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_depreciation_account_id"
                        :options="getCompanyAccounts(companyId, 'beban_penyusutan').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Penyusutan Aset:"
                        placeholder="Pilih Akun Penyusutan"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_depreciation_account_id`]"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_accumulated_depreciation_account_id"
                        :options="getCompanyAccounts(companyId, 'akumulasi_penyusutan').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Akumulasi Penyusutan:"
                        placeholder="Pilih Akun Akumulasi Penyusutan"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_accumulated_depreciation_account_id`]"
                    />
                    
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_amortization_account_id"
                        :options="getCompanyAccounts(companyId, 'beban_amortisasi').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Amortisasi Aset:"
                        placeholder="Pilih Akun Amortisasi"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_amortization_account_id`]"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_prepaid_amortization_account_id"
                        :options="getCompanyAccounts(companyId, 'aset_lancar_lainnya').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Amortisasi Dibayar Dimuka:"
                        placeholder="Pilih Akun Amortisasi Dibayar Dimuka"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_prepaid_amortization_account_id`]"
                    />
                    
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_rental_cost_account_id"
                        :options="getCompanyAccounts(companyId, 'beban').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Biaya Sewa Aset:"
                        placeholder="Pilih Akun Biaya Sewa"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_rental_cost_account_id`]"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_acquisition_payable_account_id"
                        :options="getCompanyAccounts(companyId, 'liabilitas_jangka_pendek').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Hutang Pembelian Aset:"
                        placeholder="Pilih Akun Hutang Pembelian Aset"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_acquisition_payable_account_id`]"
                    />

                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_financing_payable_account_id"
                        :options="getCompanyAccounts(companyId, 'liabilitas_jangka_panjang').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Hutang Leasing Aset:"
                        placeholder="Pilih Akun Hutang Leasing Aset"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_financing_payable_account_id`]"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_sale_profit_account_id"
                        :options="getCompanyAccounts(companyId, 'pendapatan_lainnya').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Laba Penjualan Aset:"
                        placeholder="Pilih Akun Laba Penjualan Aset"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_sale_profit_account_id`]"
                    />

                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_sale_loss_account_id"
                        :options="getCompanyAccounts(companyId, 'beban_lainnya').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Rugi Penjualan Aset:"
                        placeholder="Pilih Akun Rugi Penjualan Aset"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_sale_loss_account_id`]"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <AppSelect
                        v-model="form.companies.find(c => c.id === companyId).asset_sale_receivable_account_id"
                        :options="getCompanyAccounts(companyId, 'piutang_lainnya').map(account => ({ value: account.id, label: account.code + ' - ' + account.name }))"
                        label="Akun Piutang Penjualan Aset:"
                        placeholder="Pilih Akun Piutang Penjualan Aset"
                        :error="form.errors[`companies.${form.companies.findIndex(c => c.id === companyId)}.asset_sale_receivable_account_id`]"
                    />
                </div>
            </div>
        </div>
        
        <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ props.assetCategory ? 'Ubah' : 'Tambah' }} Kategori Aset
            </AppPrimaryButton>
            <AppUtilityButton v-if="!props.assetCategory" type="button" @click="submitForm(true)" class="mr-2">
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('asset-categories.index', filters))">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 