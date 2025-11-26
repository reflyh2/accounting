<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import DebtForm from '../Partials/DebtForm.vue';

defineProps({
    item: Object,
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
    <Head title="Ubah Piutang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Piutang</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <AppBackLink :href="route('external-receivables.index', filters)" text="Kembali ke Daftar Piutang Eksternal" />
                    </div>
                    <DebtForm
                        :item="item"
                        :companies="companies"
                        :branches="branches"
                        :partners="partners"
                        :currencies="currencies"
                        :primaryCurrency="primaryCurrency"
                        :filters="filters"
                        :accounts="accounts"
                        :defaultDebtAccountId="defaultDebtAccountId"
                        scope="external"
                        debtType="receivable"
                        indexRouteName="external-receivables.index"
                        storeRouteName="external-receivables.store"
                        updateRouteName="external-receivables.update"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


