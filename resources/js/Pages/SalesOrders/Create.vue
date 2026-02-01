<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesOrderForm from './Partials/SalesOrderForm.vue';

const props = defineProps({
    filters: Object,
    formOptions: Object,
});
</script>

<template>
    <Head title="Buat Sales Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Sales Order</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <div class="mb-6">
                    <AppBackLink :href="route('sales-orders.index', props.filters)" text="Kembali ke Daftar Sales Order" />
                </div>

                <SalesOrderForm
                    :companies="formOptions.companies"
                    :branches="formOptions.branches"
                    :currencies="formOptions.currencies"
                    :customers="formOptions.customers"
                    :products="formOptions.products"
                    :uoms="formOptions.uoms"
                    :locations="formOptions.locations"
                    :channels="formOptions.channels"
                    :paymentMethods="formOptions.paymentMethods"
                    :paymentTerms="formOptions.paymentTerms"
                    :companyBankAccounts="formOptions.companyBankAccounts"
                    :costItems="formOptions.costItems"
                    :filters="filters"
                    :users="formOptions.users"
                    :shippingProviders="formOptions.shippingProviders"
                    :shippingTypeOptions="formOptions.shippingTypeOptions"
                    mode="create"
                    submit-label="Simpan Sales Order"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
