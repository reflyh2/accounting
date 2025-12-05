<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import InternalDebtForm from './Partials/InternalDebtForm.vue';

defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    counterpartyBranches: Array,
    currencies: Array,
    accounts: Array,
    counterPartyAccounts: Array,
});
</script>

<template>
    <Head title="Buat Hutang / Piutang Internal" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Hutang / Piutang Internal Baru</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('internal-debts.index', filters)" text="Kembali ke Daftar Hutang Internal" />
                        </div>
                        <InternalDebtForm
                            :companies="companies"
                            :branches="branches"
                            :counterparty-branches="counterpartyBranches"
                            :currencies="currencies"
                            :filters="filters"
                            :accounts="accounts"
                            :counterPartyAccounts="counterPartyAccounts"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


