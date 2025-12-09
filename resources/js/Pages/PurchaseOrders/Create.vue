<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PurchaseOrderForm from './Partials/PurchaseOrderForm.vue';

const props = defineProps({
    filters: Object,
    companies: Array,
    branches: Array,
    currencies: Array,
    suppliers: Array,
    products: Array,
    uoms: Array,
});

const initialLine = () => ({
    product_variant_id: '',
    uom_id: '',
    quantity: 1,
    unit_price: 0,
    tax_rate: 0,
    description: '',
    expected_date: '',
});

const form = useForm({
    company_id: '',
    branch_id: '',
    partner_id: '',
    currency_id: props.currencies?.[0]?.id || '',
    order_date: new Date().toISOString().slice(0, 10),
    expected_date: '',
    supplier_reference: '',
    payment_terms: '',
    exchange_rate: 1,
    notes: '',
    lines: [initialLine()],
});

const submit = () => {
    form.post(route('purchase-orders.store'));
};
</script>

<template>
    <Head title="Buat Purchase Order" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Purchase Order</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <div class="mb-6">
                    <AppBackLink :href="route('purchase-orders.index', props.filters)" text="Kembali ke Daftar Purchase Order" />
                </div>

                <PurchaseOrderForm
                    :form="form"
                    :companies="companies"
                    :branches="branches"
                    :currencies="currencies"
                    :suppliers="suppliers"
                    :products="products"
                    :uoms="uoms"
                    :filters="filters"
                    mode="create"
                    submit-label="Simpan Purchase Order"
                    :on-submit="submit"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

