<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import { ref, watch, onMounted, computed } from 'vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppUtilityButton from '@/Components/AppUtilityButton.vue';
import AlertNotification from '@/Components/AlertNotification.vue';
import { formatNumber } from '@/utils/numberFormat';

const page = usePage();

const props = defineProps({
    item: Object,
    companies: Array,
    branches: Array,
    counterpartyBranches: Array,
    currencies: Array,
    debts: Array,
    accounts: Array,
    counterpartyAccounts: Array,
    filters: Object,
    paymentStatusOptions: Object,
    paymentMethodOptions: Object,
});

const form = useForm({
    company_id: props.item?.branch?.branch_group?.company_id || null,
    branch_id: props.item?.branch_id || null,
    counterparty_company_id: null,
    counterparty_branch_id: null,
    currency_id: props.item?.currency_id || page.props.primaryCurrency?.id || null,
    exchange_rate: props.item?.exchange_rate || 1,
    payment_date: props.item?.payment_date || new Date().toISOString().split('T')[0],
    account_id: props.item?.account_id || null,
    payment_method: props.item?.payment_method || '',
    reference_number: props.item?.reference_number || '',
    notes: props.item?.notes || '',
    amount: props.item?.amount || 0,
    details: [],
});

const submitted = ref(false);
const selectedCompany = ref(form.company_id);
const selectedCounterpartyCompany = ref(null);
const debtsList = computed(() => props.debts || []);
const debtInputs = ref({});
const accounts = ref(props.accounts || []);
const counterpartyAccounts = ref(props.counterpartyAccounts || []);
const notification = ref({ show: false, type: 'success', message: '' });

const currencyOptions = computed(() => props.currencies.map(c => ({ value: c.id, label: `${c.code} - ${c.name}` })));
const currentCurrencySymbol = computed(() => {
    const c = props.currencies.find(c => c.id == form.currency_id);
    return c?.symbol || page.props.primaryCurrency?.symbol || '';
});

const paymentMethodOptions = computed(() => Object.entries(props.paymentMethodOptions).map(([value, label]) => ({ value, label })));

const totalAmount = computed(() => {
    return Object.values(debtInputs.value).reduce((sum, val) => sum + (Number(val) || 0), 0);
});
const primaryCurrencyAmount = computed(() => {
    if (form.currency_id == page.props.primaryCurrency?.id) return totalAmount.value;
    return totalAmount.value * (Number(form.exchange_rate) || 1);
});

const maxAmount = computed(() => {
    return debtsList.value.reduce((sum, d) => sum + (Number(d.remaining_amount) || 0), 0);
});

const accountOptions = computed(() =>
    props.accounts.filter(a => a.type === 'kas_bank').map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))
);
const counterpartyAccountOptions = computed(() =>
    props.counterpartyAccounts.filter(a => a.type === 'kas_bank').map(a => ({ value: a.id, label: `${a.code} - ${a.name}` }))
);

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

async function refreshDebts() {
    router.reload({
        only: ['branches', 'counterpartyBranches', 'currencies', 'debts', 'accounts', 'counterpartyAccounts'],
        data: {
            company_id: selectedCompany.value,
            branch_id: form.branch_id,
            counterparty_company_id: selectedCounterpartyCompany.value,
            counterparty_branch_id: form.counterparty_branch_id,
            currency_id: form.currency_id,
        }
    });
}

watch(selectedCompany, () => {
    form.currency_id = page.props.primaryCurrency?.id || null;
    form.exchange_rate = 1;
    router.reload({ only: ['branches', 'currencies', 'accounts'], data: { company_id: selectedCompany.value } });
});

watch(selectedCounterpartyCompany, () => {
    router.reload({ only: ['counterpartyBranches', 'counterpartyAccounts'], data: { counterparty_company_id: selectedCounterpartyCompany.value } });
});

watch(() => form.branch_id, async () => {
    await refreshDebts();
}, { immediate: true });

watch(() => form.counterparty_branch_id, () => {
    refreshDebts();
});
watch(() => form.currency_id, () => {
    updateExchangeRate();
    refreshDebts();
});

onMounted(async () => {
    selectedCompany.value = props.item?.branch?.branch_group?.company_id || (props.companies.length > 1 ? null : props.companies[0]?.id);
    // accounts are received as props and will be refreshed via router.reload when company/counterparty selection changes
    // initialize inputs from existing details (edit)
    if (props.item?.details?.length) {
        const next = {};
        props.item.details.forEach(d => {
            next[d.internal_debt_id] = Number(d.amount) || 0;
        });
        debtInputs.value = next;
        form.amount = totalAmount.value;
    }
});

function clampAmount(val, max) {
    const num = Number(val) || 0;
    if (num < 0) return 0;
    if (max != null && num > Number(max)) return Number(max);
    return num;
}
function allocateFromTotal() {
    const total = Number(form.amount) || 0;
    if (total > maxAmount.value) {
        form.amount = maxAmount.value;
    }
    let remaining = total;
    // earlier debts first by issue date
    const sorted = [...(debtsList.value || [])].sort((a, b) => {
        const da = new Date(a.issue_date).getTime() || 0;
        const db = new Date(b.issue_date).getTime() || 0;
        return da - db;
    });
    const next = {};
    for (const d of sorted) {
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
}
function updateTotalAmount() {
    const next = {};
    for (const d of debtsList.value) {
        const raw = debtInputs.value[d.id];
        next[d.id] = clampAmount(raw, d.remaining_amount);
    }
    debtInputs.value = next;
    form.amount = totalAmount.value;
}

function submitForm(createAnother = false) {
    submitted.value = true;
    // build details from inputs
    form.details = Object.entries(debtInputs.value)
        .filter(([_, amt]) => Number(amt) > 0)
        .map(([id, amt]) => ({ internal_debt_id: Number(id), amount: Number(amt) }));
    if (props.item) {
        form.put(route('internal-debt-payments.update', props.item.id), {
            preserveScroll: true,
            onFinish: () => { submitted.value = false; },
        });
    } else {
        form.post(route('internal-debt-payments.store'), {
            preserveScroll: true,
            onSuccess: () => {
                submitted.value = false;
                if (createAnother) {
                    form.reset();
                    debtInputs.value = {};
                    form.amount = 0;
                }
            },
            onFinish: () => { submitted.value = false; },
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
                        :options="props.companies.map(c => ({ value: c.id, label: c.name }))"
                        label="Perusahaan (Peminjam):"
                        placeholder="Pilih Perusahaan"
                        required
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="props.branches?.map(b => ({ value: b.id, label: b.name })) || []"
                        label="Cabang (Peminjam):"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.item"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="selectedCounterpartyCompany"
                        :options="props.companies.map(c => ({ value: c.id, label: c.name }))"
                        label="Perusahaan (Pemberi):"
                        placeholder="Pilih Perusahaan Pemberi"
                    />
                    <AppSelect
                        v-model="form.counterparty_branch_id"
                        :options="props.counterpartyBranches?.map(b => ({ value: b.id, label: b.name })) || []"
                        label="Cabang (Pemberi):"
                        placeholder="Pilih Cabang Pemberi"
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
                        label="Tanggal:"
                        :error="form.errors.payment_date"
                        required
                    />
                    <AppSelect
                        v-model="form.account_id"
                        :options="accountOptions"
                        label="Rekening Kas/Bank:"
                        placeholder="Pilih Akun"
                        :error="form.errors.account_id"
                        required
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
                <div v-if="form.payment_method === 'transfer'">
                    <AppSelect
                        v-model="form.counterparty_account_id"
                        :options="counterpartyAccountOptions"
                        label="Rekening Pihak Pemberi (Kas/Bank):"
                        placeholder="Pilih Akun"
                        :error="form.errors.counterparty_account_id"
                        :disabled="!selectedCounterpartyCompany"
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
                <div class="colspan-2">
                    <AppInput
                        v-model="form.amount"
                        :numberFormat="true"
                        label="Total Pembayaran:"
                        :max="maxAmount"
                        :error="form.errors.amount"
                        :prefix="currentCurrencySymbol"
                        @keyup="allocateFromTotal()"
                        placeholder="0"
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
                <h3 class="text-lg font-semibold mb-2">Informasi Pembayaran Internal</h3>
                <p class="mb-2">Pilih hutang/piutang internal yang akan dibayar dan alokasikan jumlahnya.</p>
                <ul class="list-disc list-inside">
                    <li>Pilih Perusahaan dan Cabang Peminjam.</li>
                    <li>Pilih Perusahaan dan Cabang Pemberi.</li>
                    <li>Pilih mata uang dan tanggal pembayaran.</li>
                    <li>Isi jumlah pembayaran per dokumen pada tabel di bawah.</li>
                </ul>
            </div>
        </div>

        <div class="overflow-x-auto mt-6">
            <h3 class="text-lg font-semibold mb-2">Dokumen Hutang/Piutang Belum Lunas</h3>
            <p v-if="form.errors.details" class="text-sm text-red-600 mb-2">{{ form.errors.details }}</p>
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Nomor</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Tgl Terbit</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Jumlah</th>
                        <th class="border border-gray-300 text-sm min-w-28 px-1.5 py-1.5">Sisa</th>
                        <th class="border border-gray-300 text-sm min-w-36 px-1.5 py-1.5">Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in debtsList" :key="d.id">
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5">{{ d.number }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5">{{ new Date(d.issue_date).toLocaleDateString('id-ID') }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5 text-right">{{ currentCurrencySymbol }} {{ formatNumber(d.amount) }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5 text-right">{{ currentCurrencySymbol }} {{ formatNumber(d.remaining_amount) }}</td>
                        <td class="border border-gray-300 text-sm px-1.5 py-1.5">
                            <AppInput
                                v-model="debtInputs[d.id]"
                                :numberFormat="true"
                                :margins="{ top: 0, right: 0, bottom: 0, left: 0 }"
                                :max="d.remaining_amount"
                                :prefix="currentCurrencySymbol"
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
            <AppUtilityButton v-if="!props.item" type="button" @click="submitForm(true)" :disabled="form.processing || submitted" class="mr-2">
                Simpan & Buat Lagi
            </AppUtilityButton>
            <AppSecondaryButton @click="$inertia.visit(route('internal-debt-payments.index', filters))" :disabled="form.processing || submitted">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
    <AlertNotification
        v-if="notification.show"
        :type="notification.type"
        :message="notification.message"
        @close="notification.show = false"
    />
</template>


