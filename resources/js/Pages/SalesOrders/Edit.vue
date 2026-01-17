<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesOrderForm from './Partials/SalesOrderForm.vue';

const props = defineProps({
    salesOrder: Object,
    filters: Object,
    formOptions: Object,
});

// Map salesOrder lines to include product_id from variant
const mappedSalesOrder = {
    ...props.salesOrder,
    lines: props.salesOrder.lines?.map(line => ({
        ...line,
        product_id: line.variant?.product_id || null,
        product_variant_id: line.product_variant_id,
        uom_id: line.uom_id,
        quantity: line.quantity,
        unit_price: line.unit_price ?? 0,
        tax_rate: line.tax_rate ?? 0,
        description: line.description || '',
        requested_delivery_date: line.requested_delivery_date || '',
        reservation_location_id: line.reservation_location_id || null,
    })) || [],
};
</script>

<template>
    <Head title="Ubah Sales Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Sales Order</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <div class="mb-6">
                    <AppBackLink :href="route('sales-orders.index', props.filters)" text="Kembali ke Daftar Sales Order" />
                </div>

                <SalesOrderForm
                    :sales-order="mappedSalesOrder"
                    :companies="formOptions.companies"
                    :branches="formOptions.branches"
                    :currencies="formOptions.currencies"
                    :customers="formOptions.customers"
                    :products="formOptions.products"
                    :uoms="formOptions.uoms"
                    :locations="formOptions.locations"
                    :channels="formOptions.channels"
                    :paymentMethods="formOptions.paymentMethods"
                    :companyBankAccounts="formOptions.companyBankAccounts"
                    :costItems="formOptions.costItems"
                    :users="formOptions.users"
                    :filters="filters"
                    mode="edit"
                    submit-label="Perbarui Sales Order"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
