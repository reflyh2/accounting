<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import PaymentForm from '../Partials/ExternalDebtPaymentForm.vue';

defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    currencies: Array,
    debts: Array,
    accounts: Array,
    paymentMethodOptions: Object,
});
</script>

<template>
    <Head title="Buat Pembayaran Hutang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Pembayaran Hutang</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('external-payable-payments.index', filters)" text="Kembali ke Pembayaran Hutang" />
                        </div>
                        <PaymentForm
                            :companies="companies"
                            :branches="branches"
                            :partners="partners"
                            :currencies="currencies"
                            :debts="debts"
                            :accounts="accounts"
                            :filters="filters"
                            :paymentMethodOptions="paymentMethodOptions"
                            moduleType="payable"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


