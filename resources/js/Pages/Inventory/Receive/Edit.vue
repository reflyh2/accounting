<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import ReceiveForm from './Partials/ReceiveForm.vue';

defineProps({
    transaction: Object,
    locations: Array,
    productVariants: Array,
    valuationMethods: Array,
    defaultValuationMethod: String,
});
</script>

<template>
    <Head title="Ubah Penerimaan Barang" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Ubah Penerimaan Barang</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <AppBackLink :href="route('inventory.receipts.show', transaction.id)" text="Kembali ke detail penerimaan" />

                    <ReceiveForm
                        mode="edit"
                        :transaction="transaction"
                        :locations="locations"
                        :product-variants="productVariants"
                        :valuation-methods="valuationMethods"
                        :default-valuation-method="defaultValuationMethod"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

