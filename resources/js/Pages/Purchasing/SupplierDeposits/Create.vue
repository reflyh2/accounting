<script setup>
import { computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppSecondaryButton from '@/Components/AppSecondaryButton.vue';
import AppInput from '@/Components/AppInput.vue';
import AppSelect from '@/Components/AppSelect.vue';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
    formOptions: Object,
    today: String,
    partnerId: Number,
});

const preselectedPartner = props.partnerId 
    ? props.formOptions.suppliers.find(s => s.id === props.partnerId) 
    : null;

const preselectedCompanyId = preselectedPartner && preselectedPartner.company_ids.length === 1
    ? preselectedPartner.company_ids[0]
    : (props.formOptions.companies.length === 1 ? props.formOptions.companies[0].id : null);

const form = useForm({
    company_id: preselectedCompanyId,
    branch_id: null,
    partner_id: props.partnerId || null,
    currency_id: props.formOptions.currencies?.[0]?.id || null,
    exchange_rate: 1,
    deposit_date: props.today,
    amount: 0,
    payment_method: null,
    company_bank_account_id: null,
    notes: '',
});

const filteredBranches = computed(() => {
    if (!form.company_id) return [];
    return props.formOptions.branches.filter((b) => b.company_id === form.company_id);
});

const filteredSuppliers = computed(() => {
    if (!form.company_id) return props.formOptions.suppliers;
    return props.formOptions.suppliers.filter((s) =>
        Array.isArray(s.company_ids) && s.company_ids.includes(form.company_id)
    );
});

const filteredBankAccounts = computed(() => {
    if (!form.company_id) return [];
    return props.formOptions.companyBankAccounts.filter((b) => b.company_id === form.company_id);
});

const requiresBank = computed(() => form.payment_method && form.payment_method !== 'cash');

watch(() => form.company_id, () => {
    form.branch_id = filteredBranches.value.length === 1 ? filteredBranches.value[0].id : null;
    form.partner_id = null;
    form.company_bank_account_id = null;
});

watch(() => form.payment_method, () => {
    if (!requiresBank.value) form.company_bank_account_id = null;
});

function submit() {
    form.post(route('supplier-deposits.store'), { preserveScroll: true });
}

function handleCancel() {
    if (props.partnerId) {
        router.visit(route('supplier-deposits.index', { partner_id: props.partnerId }));
    } else {
        router.visit(route('supplier-deposits.index'));
    }
}
</script>

<template>
    <Head title="Buat Deposit Pemasok" />

    <AuthenticatedLayout>
        <template #header><h2>Buat Deposit Pemasok</h2></template>

        <div class="mx-auto bg-white shadow-sm sm:rounded border border-gray-200 p-6">
            <form @submit.prevent="submit" class="space-y-4 max-w-3xl">
                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.company_id"
                        :options="formOptions.companies.map((c) => ({ value: c.id, label: c.name }))"
                        label="Perusahaan:"
                        placeholder="Pilih Perusahaan"
                        required
                        :error="form.errors.company_id"
                    />
                    <AppSelect
                        v-model="form.branch_id"
                        :options="filteredBranches.map((b) => ({ value: b.id, label: b.name }))"
                        label="Cabang:"
                        placeholder="Pilih Cabang"
                        :disabled="!form.company_id"
                        :error="form.errors.branch_id"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.partner_id"
                        :options="filteredSuppliers.map((s) => ({ value: s.id, label: `${s.code} — ${s.name}` }))"
                        label="Pemasok:"
                        placeholder="Pilih Pemasok"
                        :disabled="!form.company_id"
                        required
                        :error="form.errors.partner_id"
                    />
                    <AppSelect
                        v-model="form.currency_id"
                        :options="formOptions.currencies.map((c) => ({ value: c.id, label: `${c.code} — ${c.name}` }))"
                        label="Mata Uang:"
                        required
                        :error="form.errors.currency_id"
                    />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <AppInput
                        v-model="form.deposit_date"
                        type="date"
                        label="Tanggal Deposit:"
                        required
                        :error="form.errors.deposit_date"
                    />
                    <AppInput
                        v-model="form.amount"
                        :numberFormat="true"
                        label="Jumlah:"
                        required
                        :error="form.errors.amount"
                    />
                    <AppInput
                        v-model="form.exchange_rate"
                        :numberFormat="true"
                        label="Kurs:"
                        :error="form.errors.exchange_rate"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <AppSelect
                        v-model="form.payment_method"
                        :options="formOptions.paymentMethods.map((m) => ({ value: m.value, label: m.label }))"
                        label="Metode Pembayaran:"
                        placeholder="Pilih metode"
                        :error="form.errors.payment_method"
                    />
                    <AppSelect
                        v-if="requiresBank"
                        v-model="form.company_bank_account_id"
                        :options="filteredBankAccounts.map((b) => ({ value: b.id, label: b.label }))"
                        label="Rekening Bank:"
                        placeholder="Pilih Rekening"
                        :disabled="!form.company_id"
                        :error="form.errors.company_bank_account_id"
                    />
                </div>

                <AppTextarea
                    v-model="form.notes"
                    label="Catatan:"
                    rows="3"
                    :error="form.errors.notes"
                />

                <div class="flex gap-2 justify-end">
                    <AppSecondaryButton type="button" @click="handleCancel">Batal</AppSecondaryButton>
                    <AppPrimaryButton type="submit" :disabled="form.processing">Catat Deposit</AppPrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
