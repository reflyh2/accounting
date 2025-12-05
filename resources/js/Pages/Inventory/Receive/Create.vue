<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import ReceiveForm from './Partials/ReceiveForm.vue';

defineProps({
    locations: Array,
    productVariants: Array,
    valuationMethods: Array,
    defaultValuationMethod: String,
});
</script>

<template>
    <Head title="Penerimaan Barang Baru" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Penerimaan Barang Baru</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <AppBackLink :href="route('inventory.receipts.index')" text="Kembali ke daftar penerimaan" />

                    <ReceiveForm
                        mode="create"
                        :transaction="null"
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

