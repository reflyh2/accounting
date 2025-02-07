<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PaymentForm from './Partials/PaymentForm.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: Object,
    accounts: Array,
});
</script>

<template>
    <Head title="Tambah Pembayaran Sewa" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Tambah Pembayaran Sewa</h2>
        </template>

        <div class="min-w-max sm:min-w-min md:max-w-full mx-auto">
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 mb-6">
                <div class="p-6">
                    <div class="mb-6">
                        <AppBackLink :href="route('asset-rental-payments.index', asset.id)" text="Kembali ke Daftar Pembayaran" />
                    </div>

                    <h3 class="text-lg font-semibold mb-4">Informasi Aset</h3>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-sm text-gray-600">Nama Aset</p>
                            <p class="font-medium">{{ asset?.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Biaya Sewa</p>
                            <p class="font-medium">{{ formatNumber(asset.rental_amount) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Perusahaan</p>
                            <p class="font-medium">{{ asset.branch.branch_group.company.name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Cabang</p>
                            <p class="font-medium">{{ asset.branch.name }}</p>
                        </div>
                    </div>

                    <PaymentForm
                        :asset="asset"
                        :accounts="accounts"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 