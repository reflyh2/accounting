<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import DebtForm from '../Partials/DebtForm.vue';

defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    partners: Array,
    currencies: Array,
    primaryCurrency: Object,
    accounts: Array,
    defaultDebtAccountId: [String, Number, null],
});
</script>

<template>
    <Head title="Buat Hutang Eksternal" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Hutang Eksternal</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('external-payables.index', filters)" text="Kembali ke Daftar Hutang Eksternal" />
                    </div>
                    <DebtForm
                        :companies="companies"
                        :branches="branches"
                        :partners="partners"
                        :currencies="currencies"
                        :primaryCurrency="primaryCurrency"
                        :filters="filters"
                        :accounts="accounts"
                        :defaultDebtAccountId="defaultDebtAccountId"
                        scope="external"
                        debtType="payable"
                        indexRouteName="external-payables.index"
                        storeRouteName="external-payables.store"
                        updateRouteName="external-payables.update"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
    
</template>


