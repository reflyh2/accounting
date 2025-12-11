<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PurchaseOrderForm from './Partials/PurchaseOrderForm.vue';

const props = defineProps({
    purchaseOrder: Object,
    filters: Object,
    companies: Array,
    branches: Array,
    currencies: Array,
    suppliers: Array,
    products: Array,
    uoms: Array,
});
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
                    :purchase-order="purchaseOrder"
                    :companies="companies"
                    :branches="branches"
                    :currencies="currencies"
                    :suppliers="suppliers"
                    :products="products"
                    :uoms="uoms"
                    :filters="filters"
                    mode="edit"
                    submit-label="Ubah Purchase Order"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

