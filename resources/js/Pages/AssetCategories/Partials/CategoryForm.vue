<script setup>
import { useForm } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppSelect from '@/Components/AppSelect.vue';
import { computed } from 'vue';

const props = defineProps({
    category: Object,
    companies: Array,
    filters: Object,
    accounts: {
        type: Array,
        required: true
    }
});

const form = useForm({
    name: props.category?.name || '',
    description: props.category?.description || '',
    company_ids: props.category?.companies?.map(c => c.id) || props.companies.map(c => c.id),
    fixed_asset_account_id: props.category?.fixed_asset_account_id || '',
    purchase_payable_account_id: props.category?.purchase_payable_account_id || '',
    accumulated_depreciation_account_id: props.category?.accumulated_depreciation_account_id || '',
    depreciation_expense_account_id: props.category?.depreciation_expense_account_id || '',
    prepaid_rent_account_id: props.category?.prepaid_rent_account_id || '',
    rent_expense_account_id: props.category?.rent_expense_account_id || '',
    create_another: false,
});

// Filter accounts by type and category
const assetAccounts = computed(() => props.accounts.filter(account => account.type === 'aset_tetap'));
const liabilityAccounts = computed(() => props.accounts.filter(account => ['hutang_usaha', 'hutang_usaha_lainnya', 'liabilitas_jangka_pendek'].includes(account.type)));
const accumulatedDepreciationAccounts = computed(() => props.accounts.filter(account => account.type === 'akumulasi_penyusutan'));
const expenseAccounts = computed(() => props.accounts.filter(account => account.type === 'beban_penyusutan'));
const prepaidRentAccounts = computed(() => props.accounts.filter(account => account.type === 'aset_lancar_lainnya'));
const rentExpenseAccounts = computed(() => props.accounts.filter(account => account.type === 'beban_amortisasi'));

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    
    if (props.category) {
        form.put(route('asset-categories.update', props.category.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (createAnother) {
                    form.reset();
                    form.clearErrors();
                }
            }
        });
    } else {
        form.post(route('asset-categories.store'), {
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
                    <AppInput
                        v-model="form.name"
                        label="Nama Kategori"
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

                    <div class="border-t pt-4 mt-4">
                        <h3 class="text-lg font-medium mb-4">Pengaturan Akun</h3>
                        
                        <AppSelect
                            v-model="form.fixed_asset_account_id"
                            :options="assetAccounts.map(account => ({
                                value: account.id,
                                label: account.code + ' - ' + account.name
                            }))"
                            label="Akun Aset Tetap"
                            hint="Akun yang digunakan untuk mencatat nilai aset tetap"
                            :error="form.errors.fixed_asset_account_id"
                            required
                        />

                        <AppSelect
                            v-model="form.purchase_payable_account_id"
                            :options="liabilityAccounts.map(account => ({
                                value: account.id,
                                label: account.code + ' - ' + account.name
                            }))"
                            label="Akun Hutang Pembelian"
                            hint="Akun yang digunakan untuk mencatat hutang pembelian aset"
                            :error="form.errors.purchase_payable_account_id"
                            required
                        />

                        <AppSelect
                            v-model="form.accumulated_depreciation_account_id"
                            :options="accumulatedDepreciationAccounts.map(account => ({
                                value: account.id,
                                label: account.code + ' - ' + account.name
                            }))"
                            label="Akun Akumulasi Penyusutan"
                            hint="Akun yang digunakan untuk mencatat akumulasi penyusutan aset"
                            :error="form.errors.accumulated_depreciation_account_id"
                            required
                        />

                        <AppSelect
                            v-model="form.depreciation_expense_account_id"
                            :options="expenseAccounts.map(account => ({
                                value: account.id,
                                label: account.code + ' - ' + account.name
                            }))"
                            label="Akun Beban Penyusutan"
                            hint="Akun yang digunakan untuk mencatat beban penyusutan aset"
                            :error="form.errors.depreciation_expense_account_id"
                            required
                        />

                        <AppSelect
                            v-model="form.prepaid_rent_account_id"
                            :options="prepaidRentAccounts.map(account => ({
                                value: account.id,
                                label: account.code + ' - ' + account.name
                            }))"
                            label="Akun Sewa Dibayar Dimuka"
                            hint="Akun yang digunakan untuk mencatat pembayaran sewa dimuka"
                            :error="form.errors.prepaid_rent_account_id"
                            required
                        />

                        <AppSelect
                            v-model="form.rent_expense_account_id"
                            :options="rentExpenseAccounts.map(account => ({
                                value: account.id,
                                label: account.code + ' - ' + account.name
                            }))"
                            label="Akun Beban Sewa"
                            hint="Akun yang digunakan untuk mencatat beban sewa"
                            :error="form.errors.rent_expense_account_id"
                            required
                        />
                    </div>
                </div>
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Kategori</h3>
                <p class="mb-2">Kategori adalah kelompok dari aset yang memiliki karakteristik yang sama. Contoh: Kendaraan, Mesin, Bangunan, dll.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih perusahaan yang sesuai</li>
                    <li>Deskripsi kategori adalah deskripsi singkat yang menjelaskan kategori.</li>
                    <li>Pengaturan akun akan digunakan untuk pencatatan jurnal otomatis.</li>
                </ul>
            </div>
        </div>

        <div class="flex items-center mt-4">
            <AppPrimaryButton type="submit" class="mr-2">
                {{ category ? 'Ubah' : 'Buat' }} Kategori
            </AppPrimaryButton>
            <AppUtilityButton
                v-if="!category"
                type="button"
                @click="submitForm(true)"
                class="mr-2"
            >
                Tambah & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton
                @click="$inertia.visit(route('asset-categories.index', filters))"
            >
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template> 