<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import DeliveryForm from './Partials/DeliveryForm.vue';

const props = defineProps({
    salesOrder: Object,
    formOptions: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <Head title="Buat Pengiriman Penjualan" />
    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Pengiriman Penjualan</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6 space-y-6">
                <AppBackLink :href="route('sales-deliveries.index')" text="Kembali ke daftar pengiriman" />

                <div v-if="!salesOrder" class="border border-dashed border-gray-300 rounded-lg p-8 text-center space-y-4">
                    <p class="text-gray-600">
                        Pilih Sales Order yang sudah dikonfirmasi untuk membuat pengiriman.
                    </p>
                    <Link :href="route('sales-orders.index')">
                        <AppPrimaryButton>Lihat Sales Order</AppPrimaryButton>
                    </Link>
                </div>

                <DeliveryForm
                    v-else
                    :sales-order="salesOrder"
                    :locations="formOptions.locations"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

