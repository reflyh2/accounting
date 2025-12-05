<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PurchaseOrderForm from './Partials/PurchaseOrderForm.vue';

const props = defineProps({
    purchaseOrder: Object,
    filters: Object,
    formOptions: Object,
});

const form = useForm({
    company_id: props.purchaseOrder.company_id,
    branch_id: props.purchaseOrder.branch_id,
    partner_id: props.purchaseOrder.partner_id,
    currency_id: props.purchaseOrder.currency_id,
    order_date: props.purchaseOrder.order_date,
    expected_date: props.purchaseOrder.expected_date,
    supplier_reference: props.purchaseOrder.supplier_reference,
    payment_terms: props.purchaseOrder.payment_terms,
    exchange_rate: props.purchaseOrder.exchange_rate,
    notes: props.purchaseOrder.notes,
    lines: props.purchaseOrder.lines.map((line) => ({
        product_variant_id: line.product_variant_id,
        uom_id: line.uom_id,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
        tax_rate: Number(line.tax_rate),
        description: line.description,
        expected_date: line.expected_date,
    })),
});

const submit = () => {
    form.put(route('purchase-orders.update', props.purchaseOrder.id));
};
</script>

<template>
    <Head title="Ubah Purchase Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Purchase Order {{ purchaseOrder.order_number }}</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <div class="mb-6">
                    <AppBackLink :href="route('purchase-orders.index', props.filters)" text="Kembali ke Daftar Purchase Order" />
                </div>

                <PurchaseOrderForm
                    :form="form"
                    :form-options="formOptions"
                    mode="edit"
                    submit-label="Perbarui Purchase Order"
                    :on-submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

