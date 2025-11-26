<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, watch, onMounted, computed } from 'vue';
import axios from 'axios';

const page = usePage();

const props = defineProps({
    item: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    currencies: Array,
    debts: Array,
    accounts: Array,
    filters: Object,
    moduleType: String, // 'payable' | 'receivable'
});

const form = useForm({
    company_id: props.item?.branch?.branch_group?.company_id || null,
    branch_id: props.item?.branch_id || null,
    partner_id: props.item?.partner_id || null,
    account_id: props.item?.account_id || null,
    currency_id: props.item?.currency_id || page.props.primaryCurrency?.id || null,
    exchange_rate: props.item?.exchange_rate || 1,
    payment_date: props.item?.payment_date || new Date().toISOString().split('T')[0],
    amount: props.item?.amount || 0,
    payment_method: props.item?.payment_method || 'cash',
    partner_bank_account_id: props.item?.partner_bank_account_id || null,
    instrument_date: props.item?.instrument_date || null,
    withdrawal_date: props.item?.withdrawal_date || null,
    reference_number: props.item?.reference_number || '',
    notes: props.item?.notes || '',
    details: props.item?.details?.map(d => ({ external_debt_id: d.external_debt_id, amount: Number(d.amount) })) || [],
});

const submitted = ref(false);
const selectedCompany = ref(form.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id));

const currencyOptions = computed(() => props.currencies.map(c => ({ value: c.id, label: `${c.code} - ${c.name}` })));
const branchOptions = computed(() => props.branches?.map(b => ({ value: b.id, label: b.name })) || []);
const partnerOptions = computed(() => props.partners?.map(p => ({ value: p.id, label: p.name })) || []);
const accountOptions = computed(() => props.accounts?.filter(a => a.type === 'kas_bank').map(a => ({ value: a.id, label: `${a.code} - ${a.name}` })) || []);
const paymentMethodOptions = ref([
    { value: 'cash', label: 'Tunai' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'cek', label: 'Cek' },
    { value: 'giro', label: 'Giro' },
]);
const partnerBankAccounts = ref([]);
const partnerBankAccountOptions = computed(() => partnerBankAccounts.value.map(b => ({ value: b.id, label: `${b.bank_name} - ${b.account_number} (${b.account_holder_name})` })));

const debtInputs = ref({});
const debtsList = computed(() => props.debts || []);
const totalAmount = computed(() => {
    return Object.values(debtInputs.value).reduce((sum, val) => sum + (Number(val) || 0), 0);
});

const currentCurrencySymbol = computed(() => {
    const currency = props.currencies.find(c => c.id == form.currency_id);
    return currency?.symbol || page.props.primaryCurrency?.symbol || '';
});

const isAllocating = ref(false);
const sortedDebts = computed(() => {
    // earlier debts first
    return [...(debtsList.value || [])].sort((a, b) => {
        const da = new Date(a.issue_date).getTime() || 0;
        const db = new Date(b.issue_date).getTime() || 0;
        return da - db;
    });
});

function clampAmount(val, max) {
    const num = Number(val) || 0;
    if (num < 0) return 0;
    if (max != null && num > Number(max)) return Number(max);
    return num;
}

function allocateFromTotal() {
    const total = Number(form.amount) || 0;
    let remaining = total;
    isAllocating.value = true;
    // reset all first
    const next = {};
    for (const d of sortedDebts.value) {
        if (remaining <= 0) {
            next[d.id] = 0;
            continue;
        }
        const cap = Number(d.remaining_amount) || 0;
        const pay = Math.min(cap, remaining);
        next[d.id] = pay;
        remaining -= pay;
    }
    debtInputs.value = next;
    isAllocating.value = false;
}

function updateTotalAmount() {  
    if (isAllocating.value) return;
    // sanitize inputs against remaining_amount
    const sanitized = {};
    for (const d of sortedDebts.value) {
        const raw = debtInputs.value[d.id];
        sanitized[d.id] = clampAmount(raw, d.remaining_amount);
    }
    debtInputs.value = sanitized;
    form.amount = totalAmount.value;
}

function updateExchangeRate() {
    if (!form.currency_id || !selectedCompany.value) {
        form.exchange_rate = 1;
        return;
    }
    const curr = props.currencies.find(c => c.id == form.currency_id);
    if (curr && curr.company_rates) {
        const companyRate = curr.company_rates.find(rate => rate.company_id == selectedCompany.value);
        if (companyRate) {
            form.exchange_rate = companyRate.exchange_rate;
        }
    }
}

watch(() => form.currency_id, () => {
    updateExchangeRate();
    router.reload({ only: ['debts'], data: { company_id: selectedCompany.value, branch_id: form.branch_id, partner_id: form.partner_id, currency_id: form.currency_id } });
});

watch(selectedCompany, (newCompanyId) => {
    if (!props.item) {
        form.currency_id = page.props.primaryCurrency?.id || null;
        form.exchange_rate = 1;
    }
    router.reload({ only: ['branches', 'currencies', 'partners', 'debts', 'accounts'], data: { company_id: newCompanyId, branch_id: form.branch_id, partner_id: form.partner_id, currency_id: form.currency_id } });
}, { immediate: true });

watch(() => form.branch_id, () => {
    router.reload({ only: ['debts'], data: { company_id: selectedCompany.value, branch_id: form.branch_id, partner_id: form.partner_id, currency_id: form.currency_id } });
});
watch(() => form.partner_id, () => {
    router.reload({ only: ['debts'], data: { company_id: selectedCompany.value, branch_id: form.branch_id, partner_id: form.partner_id, currency_id: form.currency_id } });
    loadPartnerBankAccounts();
});

onMounted(() => {
    selectedCompany.value = props.item?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
    if (!props.item && props.branches && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
    // initialize debt inputs from existing details (edit)
    if (form.details && form.details.length) {
        form.details.forEach(d => {
            debtInputs.value[d.external_debt_id] = Number(d.amount) || 0;
        });
        form.amount = totalAmount.value;
    }
    loadPartnerBankAccounts();
});

async function loadPartnerBankAccounts() {
    partnerBankAccounts.value = [];
    form.partner_bank_account_id = null;
    if (!form.partner_id) return;
    try {
        const { data } = await axios.get(route('partners.bank-accounts', form.partner_id));
        partnerBankAccounts.value = data?.data || [];
        const primary = partnerBankAccounts.value.find(b => b.is_primary);
        if (primary) {
            form.partner_bank_account_id = primary.id;
        }
    } catch (e) {
        partnerBankAccounts.value = [];
    }
}

function submitForm() {
    submitted.value = true;
    // build details from inputs
    form.details = Object.entries(debtInputs.value)
        .filter(([_, amt]) => Number(amt) > 0)
        .map(([id, amt]) => ({ external_debt_id: Number(id), amount: Number(amt) }));
    form.amount = totalAmount.value;
    if (props.item) {
        form.put(route(props.moduleType === 'payable' ? 'external-payable-payments.update' : 'external-receivable-payments.update', props.item.id), {
            preserveScroll: true,
            onFinish: () => { submitted.value = false; }
        });
    } else {
        form.post(route(props.moduleType === 'payable' ? 'external-payable-payments.store' : 'external-receivable-payments.store'), {
            preserveScroll: true,
            onFinish: () => { submitted.value = false; }
        });
    }
}
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-4">
        <div class="flex justify-between">
            <div class="w-2/3 max-w-2xl mr-8">
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCompany"
                        :options="props.companies.map(company => ({ value: company.id, label: company.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        required
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="branchOptions"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.partner_id"
                        :options="partnerOptions"
                        label="Partner:"
                        placeholder="Pilih Partner"
                        :error="form.errors.partner_id"
                    />
                    <AppSelect
                        v-model="form.account_id"
                        :options="accountOptions"
                        label="Akun Kas/Bank:"
                        placeholder="Pilih Akun"
                        :error="form.errors.account_id"
                        required
                    />
                </div>
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
                        v-model="form.payment_date"
                        type="date"
                        label="Tanggal Pembayaran:"
                        :error="form.errors.payment_date"
                        required
                    />
                    <AppInput
                        v-model="form.amount"
                        :numberFormat="true"
                        label="Total Pembayaran:"
                        :error="form.errors.amount"
                        :prefix="currentCurrencySymbol"
                        @keyup="allocateFromTotal()"
                        placeholder="0"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.payment_method"
                        :options="paymentMethodOptions"
                        label="Metode Pembayaran:"
                        :error="form.errors.payment_method"
                        required
                    />
                    <AppInput
                        v-model="form.reference_number"
                        label="No. Referensi:"
                        :error="form.errors.reference_number"
                    />
                </div>
                <div v-if="form.payment_method === 'transfer'" class="colspan-2">
                    <AppSelect
                        v-model="form.partner_bank_account_id"
                        :options="partnerBankAccountOptions"
                        label="Rekening Bank Partner:"
                        placeholder="Pilih Rekening"
                        :error="form.errors.partner_bank_account_id"
                        :disabled="!form.partner_id"
                        required
                    />
                </div>
                <div v-if="form.payment_method === 'cek' || form.payment_method === 'giro'" class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.instrument_date"
                        type="date"
                        label="Tanggal Cek/Giro:"
                        :error="form.errors.instrument_date"
                        required
                    />
                    <AppInput
                        v-model="form.withdrawal_date"
                        type="date"
                        label="Tanggal Pencairan:"
                        :error="form.errors.withdrawal_date"
                        required
                    />
                </div>
                <AppInput
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                />
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Pembayaran</h3>
                <ul class="list-disc list-inside">
                    <li>Pilih Perusahaan dan Cabang.</li>
                    <li>Pilih Partner untuk memfilter dokumen hutang/piutang.</li>
                    <li>Masukkan jumlah pembayaran per dokumen pada tabel.</li>
                    <li>Pilih akun kas/bank sebagai sumber/tujuan dana.</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto mt-6">
            <h3 class="text-lg font-semibold mb-2">Dokumen Belum Lunas</h3>
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Nomor</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Tanggal</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Jumlah</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Sisa</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in debtsList" :key="d.id">
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5">{{ d.number }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5">{{ new Date(d.issue_date).toLocaleDateString('id-ID') }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5 text-right">{{ d.currency?.symbol }} {{ (d.amount)?.toLocaleString('id-ID') }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5 text-right">{{ d.currency?.symbol }} {{ (d.remaining_amount)?.toLocaleString('id-ID') }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5">
                            <AppInput
                                v-model="debtInputs[d.id]"
                                :numberFormat="true"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                :max="d.remaining_amount"
                                :prefix="d.currency?.symbol"
                                @keyup="updateTotalAmount()"
                                placeholder="0"
                            />
                        </td>
                    </tr>
                    <tr v-if="!debtsList || debtsList.length === 0">
                        <td colspan="5" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada dokumen yang belum lunas.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" :disabled="form.processing || submitted" class="mr-2">
                {{ props.item ? 'Ubah' : 'Simpan' }} Pembayaran
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route(props.moduleType === 'payable' ? 'external-payable-payments.index' : 'external-receivable-payments.index', filters))" :disabled="form.processing || submitted">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>


