<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    asset: Object,
    payment: Object,
});

function formatDate(date) {
    return date ? new Date(date).toLocaleDateString('id-ID') : '-';
}

const statusLabels = {
    'pending': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Menunggu</span>',
    'paid': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Lunas</span>',
    'overdue': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Terlambat</span>'
};
</script>

<template>
    <Head title="Detail Pembayaran Sewa" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Pembayaran Sewa</h2>
        </template>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

                    <h3 class="text-lg font-semibold mb-4">Detail Pembayaran</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Periode Mulai</p>
                            <p class="font-medium">{{ formatDate(payment.period_start) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Periode Selesai</p>
                            <p class="font-medium">{{ formatDate(payment.period_end) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tanggal Pembayaran</p>
                            <p class="font-medium">{{ formatDate(payment.payment_date) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Jumlah</p>
                            <p class="font-medium">{{ formatNumber(payment.amount) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Status</p>
                            <p class="font-medium" v-html="statusLabels[payment.status]"></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-600">Catatan</p>
                            <p class="font-medium">{{ payment.notes || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 