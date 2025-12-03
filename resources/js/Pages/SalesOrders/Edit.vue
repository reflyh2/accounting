<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import SalesOrderForm from './Partials/SalesOrderForm.vue';

const props = defineProps({
    salesOrder: Object,
    filters: Object,
    formOptions: Object,
});

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

const mapLine = (line) => ({
    product_variant_id: line.product_variant_id,
    uom_id: line.uom_id,
    quantity: line.quantity,
    unit_price: line.unit_price ?? '',
    tax_rate: line.tax_rate ?? '',
    description: line.description || '',
    requested_delivery_date: line.requested_delivery_date || '',
    reservation_location_id: line.reservation_location_id || '',
});

const form = useForm({
    company_id: props.salesOrder.company_id,
    branch_id: props.salesOrder.branch_id,
    partner_id: props.salesOrder.partner_id,
    price_list_id: props.salesOrder.price_list_id || '',
    currency_id: props.salesOrder.currency_id,
    order_date: props.salesOrder.order_date,
    expected_delivery_date: props.salesOrder.expected_delivery_date || '',
    quote_valid_until: props.salesOrder.quote_valid_until || '',
    customer_reference: props.salesOrder.customer_reference || '',
    sales_channel: props.salesOrder.sales_channel || '',
    payment_terms: props.salesOrder.payment_terms || '',
    exchange_rate: props.salesOrder.exchange_rate || 1,
    reserve_stock: props.salesOrder.reserve_stock,
    notes: props.salesOrder.notes || '',
    lines: props.salesOrder.lines?.length ? props.salesOrder.lines.map(mapLine) : [initialLine()],
});

const submit = () => {
    form.put(route('sales-orders.update', props.salesOrder.id));
};
</script>

<template>
    <Head title="Ubah Sales Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Sales Order</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <div class="mb-6">
                    <AppBackLink :href="route('sales-orders.index', props.filters)" text="Kembali ke Daftar Sales Order" />
                </div>

                <SalesOrderForm
                    :form="form"
                    :form-options="formOptions"
                    mode="edit"
                    submit-label="Perbarui Sales Order"
                    :on-submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

