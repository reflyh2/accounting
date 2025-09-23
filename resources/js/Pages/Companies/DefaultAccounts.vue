<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppSelect from '@/Components/AppSelect.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    company: Object,
    accounts: Array,
    filters: Object,
});

const form = useForm({
    default_receivable_account_id: props.company.default_receivable_account_id,
    default_payable_account_id: props.company.default_payable_account_id,
    default_revenue_account_id: props.company.default_revenue_account_id,
    default_cogs_account_id: props.company.default_cogs_account_id,
    default_retained_earnings_account_id: props.company.default_retained_earnings_account_id,
    default_interbranch_receivable_account_id: props.company.default_interbranch_receivable_account_id,
    default_interbranch_payable_account_id: props.company.default_interbranch_payable_account_id,
    default_intercompany_receivable_account_id: props.company.default_intercompany_receivable_account_id,
    default_intercompany_payable_account_id: props.company.default_intercompany_payable_account_id,
});

const submitted = ref(false);

function submitForm() {
    submitted.value = true;
    form.put(route('companies.default-accounts.update', props.company.id), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = false;
        },
        onError: () => {
            submitted.value = false;
        }
    });
}

const receivableAccounts = computed(() => {
    return props.accounts.filter(account => account.type === 'piutang_usaha' && account.is_parent === false);
});

const payableAccounts = computed(() => {
    return props.accounts.filter(account => account.type === 'hutang_usaha' && account.is_parent === false);
});

const revenueAccounts = computed(() => {
    return props.accounts.filter(account => account.type === 'pendapatan' && account.is_parent === false);
});

const cogsAccounts = computed(() => {
    return props.accounts.filter(account => account.type === 'beban_pokok_penjualan' && account.is_parent === false);
});

const retainedEarningsAccounts = computed(() => {
    return props.accounts.filter(account => account.type === 'modal' && account.is_parent === false);
});
</script>

<template>
    <Head title="Pengaturan Akun Standar" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Pengaturan Akun Standar - {{ company.name }}</h2>
        </template>

        <div>
            <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('companies.show', [company.id, filters])" text="Kembali ke Detail Perusahaan" />
                        </div>

                        <form @submit.prevent="submitForm" class="w-2/3 max-w-2xl mr-8">
                            <AppSelect
                                v-model="form.default_receivable_account_id"
                                :options="receivableAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Piutang Usaha Standar:"
                                placeholder="Pilih akun piutang usaha standar"
                                :error="form.errors.default_receivable_account_id"
                            />

                            <AppSelect
                                v-model="form.default_payable_account_id"
                                :options="payableAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Hutang Usaha Standar:"
                                placeholder="Pilih akun hutang usaha standar"
                                :error="form.errors.default_payable_account_id"
                            />

                            <AppSelect
                                v-model="form.default_revenue_account_id"
                                :options="revenueAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Pendapatan Standar:"
                                placeholder="Pilih akun pendapatan standar"
                                :error="form.errors.default_revenue_account_id"
                            />

                            <AppSelect
                                v-model="form.default_cogs_account_id"
                                :options="cogsAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun HPP Standar:"
                                placeholder="Pilih akun hpp standar"
                                :error="form.errors.default_cogs_account_id"
                            />

                            <AppSelect
                                v-model="form.default_retained_earnings_account_id"
                                :options="retainedEarningsAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Laba Ditahan Standar:"
                                placeholder="Pilih akun laba ditahan standar"
                                :error="form.errors.default_retained_earnings_account_id"
                            />

                            <AppSelect
                                v-model="form.default_interbranch_receivable_account_id"
                                :options="receivableAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Piutang Antar Cabang Standar:"
                                placeholder="Pilih akun piutang antar cabang standar"
                                :error="form.errors.default_interbranch_receivable_account_id"
                            />

                            <AppSelect
                                v-model="form.default_interbranch_payable_account_id"
                                :options="payableAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Hutang Antar Cabang Standar:"
                                placeholder="Pilih akun hutang antar cabang standar"
                                :error="form.errors.default_interbranch_payable_account_id"
                            />

                            <AppSelect
                                v-model="form.default_intercompany_receivable_account_id"
                                :options="receivableAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Piutang Antar Perusahaan Standar:"
                                placeholder="Pilih akun piutang antar perusahaan standar"
                                :error="form.errors.default_intercompany_receivable_account_id"
                            />

                            <AppSelect
                                v-model="form.default_intercompany_payable_account_id"
                                :options="payableAccounts.map(account => ({ value: account.id, label: `${account.code} - ${account.name}` }))"
                                label="Akun Hutang Antar Perusahaan Standar:"
                                placeholder="Pilih akun hutang antar perusahaan standar"
                                :error="form.errors.default_intercompany_payable_account_id"
                            />

                            <div class="mt-4">
                                <AppPrimaryButton type="submit" :disabled="submitted">
                                    Simpan Perubahan
                                </AppPrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>