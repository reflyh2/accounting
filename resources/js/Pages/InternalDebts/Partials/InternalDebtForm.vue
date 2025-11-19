<script setup>
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import { ref, watch, onMounted, computed } from 'vue';

const page = usePage();

const props = defineProps({
    debt: Object,
    companies: Array,
    branches: Array,
    counterpartyBranches: Array,
    currencies: Array,
    accounts: Array,
    counterPartyAccounts: Array,
    filters: Object,
});

const form = useForm({
    company_id: props.debt?.branch?.branch_group?.company_id || null,
    branch_id: props.debt?.branch_id || null,
    counterparty_company_id: props.debt?.company_id || null,
    counterparty_branch_id: props.debt?.counterparty_branch_id || null,
    currency_id: props.debt?.currency_id || page.props.primaryCurrency?.id || null,
    exchange_rate: props.debt?.exchange_rate || 1,
    issue_date: props.debt?.issue_date || new Date().toISOString().split('T')[0],
    due_date: props.debt?.due_date || new Date().toISOString().split('T')[0],
    amount: props.debt?.amount || 0,
    offset_account_id: props.debt?.offset_account_id || null,
    debt_account_id: props.debt?.debt_account_id || null,
    counterparty_offset_account_id: props.debt?.counterparty_offset_account_id || null,
    counterparty_debt_account_id: props.debt?.counterparty_debt_account_id || null,
    reference_number: props.debt?.reference_number || '',
    notes: props.debt?.notes || '',
});

const debtAccountOptions = computed(() => {
    return (props.accounts || []).filter(a => a.type === 'hutang_usaha').map(a => ({
        value: a.id,
        label: `${a.code} - ${a.name}`
    }));
});

const offsetAccountOptions = computed(() => {
    return (props.accounts || []).filter(a => a.type === 'kas_bank' || a.type === 'piutang_usaha').map(a => ({
        value: a.id,
        label: `${a.code} - ${a.name}`
    }));
});

const counterpartyDebtAccountOptions = computed(() => {
    return (props.counterPartyAccounts || []).filter(a => a.type === 'piutang_usaha' || a.type === 'piutang_lainnya').map(a => ({
        value: a.id,
        label: `${a.code} - ${a.name}`
    }));
});

const counterpartyOffsetAccountOptions = computed(() => {
    return (props.counterPartyAccounts || []).filter(a => a.type === 'kas_bank' || a.type === 'hutang_usaha').map(a => ({
        value: a.id,
        label: `${a.code} - ${a.name}`
    }));
});

const currencyOptions = computed(() => {
    return (props.currencies || []).map(c => ({ value: c.id, label: `${c.code} - ${c.name}` }));
});

const currentCurrencySymbol = computed(() => {
    const currency = (props.currencies || []).find(c => c.id == form.currency_id);
    return currency?.symbol || page.props.primaryCurrency?.symbol || '';
});

function numberFormat(value) {
    return Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
}

function updateExchangeRate() {
    if (!form.currency_id || !selectedCompany.value) {
        form.exchange_rate = 1;
        return;
    }
    const currency = (props.currencies || []).find(c => c.id == form.currency_id);
    if (currency && currency.company_rates) {
        const companyRate = currency.company_rates.find(rate => rate.company_id == selectedCompany.value);
        if (companyRate) {
            form.exchange_rate = companyRate.exchange_rate;
        }
    }
}

watch(() => form.currency_id, updateExchangeRate);
watch(() => form.company_id, (newCompanyId) => {
    // Reset dependent selections
    form.branch_id = null;
    form.offset_account_id = null;
    form.debt_account_id = null;
    // Refresh branches and currencies scoped to borrower company
    router.reload({
        only: ['branches', 'currencies', 'accounts'],
        data: { company_id: newCompanyId },
        preserveState: true,
        preserveScroll: true,
    });
}, { immediate: false });

watch(() => form.counterparty_company_id, (newCompanyId) => {
    // Reset dependent selections
    form.counterparty_branch_id = null;
    form.counterparty_offset_account_id = null;
    form.counterparty_debt_account_id = null;
    // Refresh creditor branches scoped to selected company
    router.reload({
        only: ['counterpartyBranches', 'counterPartyAccounts'],
        data: { counterparty_company_id: newCompanyId },
        preserveState: true,
        preserveScroll: true,
    });
}, { immediate: false });

onMounted(() => {
    if (!props.debt && props.branches && props.branches.length === 1) {
        form.branch_id = props.branches[0].id;
    }
});

// Auto-pick when exactly one option becomes available after reload
watch(() => props.branches, (newBranches) => {
    if (!props.debt && newBranches && newBranches.length === 1) {
        form.branch_id = newBranches[0].id;
    }
}, { immediate: false, deep: true });

watch(() => props.counterpartyBranches, (newBranches) => {
    if (!props.debt && newBranches && newBranches.length === 1) {
        form.counterparty_branch_id = newBranches[0].id;
    }
}, { immediate: false, deep: true });

function submitForm() {
    if (props.debt) {
        form.put(route('internal-debts.update', props.debt.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('internal-debts.store'), {
            preserveScroll: true,
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
                        v-model="form.company_id"
                        :options="(companies || []).map(c => ({ value: c.id, label: c.name }))"
                        label="Perusahaan (Peminjam):"
                        placeholder="Pilih Perusahaan"
                        :disabled="!!props.debt"
                        required
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="(branches || []).map(b => ({ value: b.id, label: b.name }))"
                        label="Cabang (Peminjam):"
                        placeholder="Pilih Cabang"
                        :error="form.errors.branch_id"
                        :disabled="!!props.debt"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.counterparty_company_id"
                        :options="(companies || []).map(c => ({ value: c.id, label: c.name }))"
                        label="Perusahaan (Pemberi Pinjaman):"
                        placeholder="Pilih Perusahaan"
                        required
                    />
                    <AppSelect
                        v-model="form.counterparty_branch_id"
                        :options="(counterpartyBranches || []).map(b => ({ value: b.id, label: b.name }))"
                        label="Cabang (Pemberi Pinjaman):"
                        placeholder="Pilih Cabang"
                        :error="form.errors.counterparty_branch_id"
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
                        v-model="form.issue_date"
                        type="date"
                        label="Tanggal Terbit:"
                        :error="form.errors.issue_date"
                        required
                    />
                    <AppInput
                        v-model="form.due_date"
                        type="date"
                        label="Jatuh Tempo:"
                        :error="form.errors.due_date"
                    />
                </div>
                <AppInput
                    v-model="form.amount"
                    :numberFormat="true"
                    :prefix="currentCurrencySymbol"
                    label="Jumlah:"
                    :error="form.errors.amount"
                    required
                />
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.offset_account_id"
                        :options="offsetAccountOptions"
                        label="Akun Offset (Peminjam):"
                        placeholder="Pilih Akun"
                        :error="form.errors.offset_account_id"
                        required
                    />
                    <AppSelect
                        v-model="form.debt_account_id"
                        :options="debtAccountOptions"
                        label="Akun Hutang (Peminjam):"
                        placeholder="Pilih Akun"
                        :error="form.errors.debt_account_id"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.counterparty_offset_account_id"
                        :options="counterpartyOffsetAccountOptions"
                        label="Akun Offset (Pemberi):"
                        placeholder="Pilih Akun"
                        :error="form.errors.counterparty_offset_account_id"
                        required
                    />
                    <AppSelect
                        v-model="form.counterparty_debt_account_id"
                        :options="counterpartyDebtAccountOptions"
                        label="Akun Piutang (Pemberi):"
                        placeholder="Pilih Akun"
                        :error="form.errors.counterparty_debt_account_id"
                        required
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <AppInput
                        v-model="form.reference_number"
                        label="Referensi:"
                        :error="form.errors.reference_number"
                        placeholder="Nomor referensi (opsional)"
                    />
                    <div></div>
                </div>
                <AppInput
                    v-model="form.notes"
                    label="Catatan:"
                    :error="form.errors.notes"
                    placeholder="Catatan (opsional)"
                />
            </div>
            <div class="w-1/3 bg-gray-100 p-4 rounded-lg text-sm">
                <h3 class="text-lg font-semibold mb-2">Informasi Hutang Internal</h3>
                <ul class="list-disc list-inside">
                    <li>Pengaju selalu merupakan pihak peminjam.</li>
                    <li>Pemberi pinjaman akan melakukan persetujuan/penolakan.</li>
                </ul>
                <div class="mt-4 p-2 bg-white border rounded">
                    <div class="text-xs text-gray-600">Jumlah dalam mata uang transaksi</div>
                    <div class="text-right font-semibold">{{ currentCurrencySymbol }} {{ numberFormat(form.amount) }}</div>
                    <div v-if="form.currency_id != null && form.currency_id != page.props.primaryCurrency?.id" class="text-right text-xs text-gray-500">
                        = {{ page.props.primaryCurrency?.symbol }} {{ numberFormat(Number(form.amount || 0) * Number(form.exchange_rate || 1)) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-6 flex items-center">
            <AppPrimaryButton type="submit" :disabled="form.processing" class="mr-2">
                {{ props.debt ? 'Ubah' : 'Simpan' }} Hutang
            </AppPrimaryButton>
            <AppSecondaryButton @click="$inertia.visit(route('internal-debts.index', filters))" :disabled="form.processing">
                Batal
            </AppSecondaryButton>
        </div>
    </form>
</template>


