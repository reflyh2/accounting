<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ref, watch, onMounted, computed } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AppPopoverSearch from '@/Components/AppPopoverSearch.vue';

const page = usePage();

const props = defineProps({
    item: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    counterpartyBranches: Array,
    currencies: Array,
    accounts: Array,
    defaultDebtAccountId: [String, Number, null],
    primaryCurrency: Object,
    filters: Object,
    scope: { type: String, required: true }, // 'external' | 'internal'
    debtType: { type: String, required: true }, // 'payable' | 'receivable'
    indexRouteName: { type: String, required: true },
    storeRouteName: { type: String, required: true },
    updateRouteName: { type: String, required: true },
});

const form = useForm({
    company_id: props.item?.branch?.branch_group?.company_id || null,
    branch_id: props.item?.branch_id || null,
    partner_id: props.item?.partner_id || null,
    counterparty_branch_id: props.item?.internal_debt?.counterparty_branch_id || null,
    counterparty_company_id: props.item?.internal_debt?.counterparty_company_id || null,
    currency_id: props.item?.currency_id || page.props.primaryCurrency?.id || null,
    exchange_rate: props.item?.exchange_rate || 1,
    issue_date: props.item?.issue_date || new Date().toISOString().split('T')[0],
    due_date: props.item?.due_date || null,
    amount: props.item?.amount || 0,
    offset_account_id: props.item?.offset_account_id || null,
    debt_account_id: props.item?.debt_account_id || props.defaultDebtAccountId || null,
    notes: props.item?.notes || '',
    status: props.item?.status || 'open',
    create_another: false,
});

const selectedCompany = ref(props.item?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));

const partnerName = ref(props.item?.partner?.name || '');
const partnerTableHeaders = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Name' },
    { key: 'actions', label: '' }
];

const currencyOptions = computed(() => props.currencies?.map(c => ({ value: c.id, label: `${c.code} - ${c.name}` })) || []);
const currentCurrencySymbol = computed(() => {
    const currency = props.currencies?.find(c => c.id == form.currency_id);
    return currency?.symbol || page.props.primaryCurrency?.symbol || '';
});

const debtAccountOptions = computed(() => {
    const accountTypes = props.debtType === 'payable' ? ['hutang_usaha', 'hutang_usaha_lainnya', 'liabilitas_jangka_pendek', 'liabilitas_jangka_panjang'] : ['piutang', 'piutang_usaha', 'piutang_lainnya', 'piutang_usaha_lainnya'];
    return props.accounts?.filter(a => accountTypes.includes(a.type))
        .map(a => ({ value: a.id, label: `${a.code} - ${a.name}` })) || [];
});

function updateExchangeRate() {
    if (!form.currency_id || !selectedCompany.value) {
        form.exchange_rate = 1;
        return;
    }
    const currency = props.currencies?.find(c => c.id == form.currency_id);
    if (currency && currency.company_rates) {
        const companyRate = currency.company_rates.find(rate => rate.company_id == selectedCompany.value);
        if (companyRate) {
            form.exchange_rate = companyRate.exchange_rate;
        }
    }
}

watch(() => form.currency_id, updateExchangeRate);

watch(selectedCompany, (newCompanyId) => {
    if (!props.item) {
        form.currency_id = page.props.primaryCurrency?.id || null;
        form.exchange_rate = 1;
    }
    router.reload({ 
        only: ['branches', 'currencies', 'partners', 'counterpartyBranches', 'accounts', 'defaultDebtAccountId'], 
        data: { company_id: newCompanyId, counterparty_company_id: form.counterparty_company_id } ,
        onFinish: () => {
            form.debt_account_id = props.defaultDebtAccountId;
        }
    });
}, { immediate: true });

// When counterparty company changes (internal scope), reload counterparty branches
watch(() => form.counterparty_company_id, (newCounterpartyCompanyId) => {
    if (props.scope !== 'internal') return;
    router.reload({
        only: ['counterpartyBranches', 'defaultDebtAccountId'],
        data: { company_id: selectedCompany.value, counterparty_company_id: newCounterpartyCompanyId },
        onStart: () => {
            form.counterparty_branch_id = null;
        },
        onFinish: () => {
            form.debt_account_id = props.defaultDebtAccountId;
        }
    });
}, { immediate: true });

watch(() => form.branch_id, () => {
    // load accounts if needed later
});

onMounted(() => {
    selectedCompany.value = props.item?.branch?.branch_group.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
    if (!props.item && props.branches && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
});

function submitForm(createAnother = false) {
    form.create_another = createAnother;
    if (props.item) {
        form.put(route(props.updateRouteName, props.item.id), { preserveScroll: true });
    } else {
        form.post(route(props.storeRouteName), { preserveScroll: true });
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
                        :options="props.companies.map(c => ({ value: c.id, label: c.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        :disabled="!!props.item"
                        required
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches?.map(b => ({ value: b.id, label: b.name })) || []"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.item"
                        required
                    />

                    <template v-if="props.scope === 'external'">
                        <AppPopoverSearch
                            v-model="form.partner_id"
                            label="Partner:"
                            placeholder="Pilih Partner"
                            :url="route('api.partners', { company_id: selectedCompany })"
                            valueKey="id"
                            :displayKeys="['name']"
                            :tableHeaders="partnerTableHeaders"
                            :initialDisplayValue="partnerName"
                            :error="form.errors.partner_id"
                            :modalTitle="'Pilih Partner'"
                            :disabled="!selectedCompany"
                            required
                        />
                    </template>

                    <template v-else>
                        <AppSelect
                            v-model="form.counterparty_company_id"
                            :options="props.companies.map(c => ({ value: c.id, label: c.name }))"
                            label="Perusahaan Lawan:"
                            placeholder="Pilih Perusahaan Lawan"
                            :error="form.errors.counterparty_company_id"
                        />
                        <AppSelect
                            v-model="form.counterparty_branch_id"
                            :options="props.counterpartyBranches?.map(b => ({ value: b.id, label: b.name })) || []"
                            label="Cabang Lawan:"
                            placeholder="Pilih Cabang Lawan"
                            :error="form.errors.counterparty_branch_id"
                            required
                        />
                    </template>

                    <AppSelect
                        v-model="form.currency_id"
                        :options="currencyOptions"
                        label="Mata Uang:"
                        placeholder="Pilih Mata Uang"
                        :error="form.errors.currency_id"
                        required
                    />

                    <AppInput
                        v-model="form.issue_date"
                        type="date"
                        label="Tanggal:"
                        :error="form.errors.issue_date"
                        required
                    />
                    <AppInput
                        v-model="form.due_date"
                        type="date"
                        label="Jatuh Tempo:"
                        :error="form.errors.due_date"
                    />

                    <AppInput
                        v-model="form.exchange_rate"
                        :numberFormat="true"
                        label="Nilai Tukar:"
                        :error="form.errors.exchange_rate"
                        required
                    />

                    <AppInput
                        v-model="form.amount"
                        :numberFormat="true"
                        :prefix="currentCurrencySymbol"
                        label="Jumlah:"
                        :error="form.errors.amount"
                        required
                    />

                    <AppSelect
                        v-model="form.debt_account_id"
                        :options="debtAccountOptions"
                        :label="`Akun ${props.debtType === 'payable' ? 'Hutang' : 'Piutang'}`"
                        placeholder="Pilih akun hutang/piutang"
                        :error="form.errors.debt_account_id"
                        required
                    />

                    <AppSelect
                        v-model="form.offset_account_id"
                        :options="(accounts || []).map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))"
                        :label="props.debtType === 'payable' ? 'Masuk ke Akun' : 'Keluar dari Akun'"
                        :placeholder="props.debtType === 'payable' ? 'Masuk ke Akun' : 'Keluar dari Akun'"
                        :error="form.errors.offset_account_id"
                    />

                    <div class="col-span-2">
                        <AppInput
                            v-model="form.notes"
                            label="Catatan:"
                            :error="form.errors.notes"
                        />
                    </div>
                </div>

                <div class="mt-6 flex items-center">
                    <AppPrimaryButton type="submit" class="mr-2">
                        {{ props.item ? 'Ubah' : 'Simpan' }}
                    </AppPrimaryButton>
                    <AppUtilityButton v-if="!props.item" type="button" @click="submitForm(true)" class="mr-2">
                        Simpan & Buat Lagi
                    </AppUtilityButton>
                    <AppSecondaryButton @click="$inertia.visit(route(props.indexRouteName, props.filters))">
                        Batal
                    </AppSecondaryButton>
                </div>
            </div>

            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Hutang/Piutang</h3>
                <p class="mb-2">Hutang/Piutang adalah kewajiban atau hak yang timbul dari transaksi jual beli atau jasa.</p>
                <ul class="list-disc list-inside">
                    <li>Tentukan tanggal hutang/piutang</li>
                    <li>Pilih perusahaan dan cabang</li>
                    <li>Pilih partner atau cabang lawan</li>
                    <li>Pilih mata uang</li>
                    <li>Masukkan jumlah hutang/piutang</li>
                    <li>Pilih akun hutang/piutang</li>
                    <li>Pilih akun lawan</li>
                    <li>Tambahkan catatan jika diperlukan</li>
                </ul>
            </div>
        </div>
    </form>
</template>


