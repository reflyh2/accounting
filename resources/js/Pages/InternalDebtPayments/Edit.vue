<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import InternalDebtPaymentForm from './Partials/InternalDebtPaymentForm.vue';

defineProps({
    item: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    counterpartyBranches: Array,
    currencies: Array,
    debts: Array,
    accounts: Array,
    counterpartyAccounts: Array,
    paymentStatusOptions: Object,
    paymentMethodOptions: Object,
});
</script>

<template>
    <Head title="Ubah Pembayaran Internal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Pembayaran Internal</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('internal-debt-payments.index', filters)" text="Kembali ke Daftar Pembayaran Internal" />
                    </div>
                    <InternalDebtPaymentForm
                        :item="item"
                        :companies="companies"
                        :branches="branches"
                        :counterparty-branches="counterpartyBranches"
                        :currencies="currencies"
                        :debts="debts"
                        :accounts="accounts"
                        :counterparty-accounts="counterpartyAccounts"
                        :filters="filters"
                        :paymentStatusOptions="paymentStatusOptions"
                        :paymentMethodOptions="paymentMethodOptions"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


