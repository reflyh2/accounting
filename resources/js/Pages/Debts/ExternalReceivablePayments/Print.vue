<script setup>
import { Head } from '@inertiajs/vue3';
import { formatNumber } from '@/utils/numberFormat';

defineProps({
    item: Object,
});
</script>

<template>
    <Head title="Cetak Penerimaan Piutang" />
    <div class="p-8 text-sm text-gray-900">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-xl font-bold">Penerimaan Piutang</h1>
                <p class="text-gray-600">Dokumen #{{ item.number }}</p>
            </div>
            <div class="text-right">
                <p class="font-semibold">{{ item.branch?.branch_group?.company?.name }}</p>
                <p>{{ item.branch?.name }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <p><span class="font-semibold">Tanggal:</span> {{ new Date(item.payment_date).toLocaleDateString('id-ID') }}</p>
                <p><span class="font-semibold">Partner:</span> {{ item.partner?.name }}</p>
                <p><span class="font-semibold">Mata Uang:</span> {{ item.currency?.code }}</p>
            </div>
            <div>
                <p><span class="font-semibold">Jumlah:</span> {{ item.currency?.symbol }} {{ formatNumber(item.amount) }}</p>
                <p><span class="font-semibold">Metode:</span> {{ item.payment_method || '-' }}</p>
                <p><span class="font-semibold">Referensi:</span> {{ item.reference_number || '-' }}</p>
            </div>
        </div>

        <div class="mb-6">
            <p class="font-semibold">Catatan</p>
            <p class="whitespace-pre-wrap">{{ item.notes || '-' }}</p>
        </div>

        <div class="mb-6">
            <p class="font-semibold">Detail Penerimaan</p>
            <table class="w-full border-collapse border border-gray-300 text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-1.5 py-1.5 text-left"># Piutang</th>
                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Tanggal Terbit</th>
                        <th class="border border-gray-300 px-1.5 py-1.5 text-right">Diterima</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in item.details" :key="d.id">
                        <td class="border border-gray-300 px-1.5 py-1.5">{{ d.external_debt?.number }}</td>
                        <td class="border border-gray-300 px-1.5 py-1.5">{{ new Date(d.external_debt?.issue_date).toLocaleDateString('id-ID') }}</td>
                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ item.currency?.symbol }} {{ formatNumber(d.amount) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-10 text-xs text-gray-500">
            <p>Dicetak pada: {{ new Date().toLocaleString('id-ID') }}</p>
        </div>
    </div>
</template>


