<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesOrderForm from './Partials/SalesOrderForm.vue';

const props = defineProps({
    filters: Object,
    formOptions: Object,
});

const today = new Date().toISOString().slice(0, 10);

const initialLine = () => ({
    product_variant_id: '',
    uom_id: '',
    quantity: 1,
    unit_price: '',
    tax_rate: '',
    description: '',
    requested_delivery_date: '',
    reservation_location_id: '',
});

const form = useForm({
    company_id: '',
    branch_id: '',
    partner_id: '',
    price_list_id: '',
    currency_id: props.formOptions.currencies?.[0]?.id || '',
    order_date: today,
    expected_delivery_date: '',
    quote_valid_until: '',
    customer_reference: '',
    sales_channel: '',
    payment_terms: '',
    exchange_rate: 1,
    reserve_stock: false,
    notes: '',
    lines: [initialLine()],
});

const submit = () => {
    form.post(route('sales-orders.store'));
};
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
                    :form="form"
                    :form-options="formOptions"
                    mode="create"
                    submit-label="Simpan Sales Order"
                    :on-submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

