<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppDangerButton from '@/Components/AppDangerButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    run: Object,
});

function reverseRun() {
    if (!confirm('Reverse allocation run ini?')) return;
    router.post(route('booking-allocations.reverse', props.run.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Allocation Run #${run.id}`" />

    <AuthenticatedLayout>
        <template #header><h2>Allocation Run #{{ run.id }}</h2></template>

        <div class="mx-auto bg-white shadow-sm sm:rounded border border-gray-200 p-6 space-y-4">
            <AppBackLink :href="route('booking-allocations.index')" text="Kembali ke daftar" />

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><strong>Pool:</strong> {{ run.cost_pool?.name }}</div>
                <div><strong>Aset:</strong> {{ run.asset?.name || '—' }}</div>
                <div><strong>Periode:</strong> {{ run.period_start }} → {{ run.period_end }}</div>
                <div><strong>Basis:</strong> {{ run.allocation_basis }}</div>
                <div><strong>Pool Amount:</strong> {{ formatNumber(run.pool_amount) }}</div>
                <div><strong>Denominator:</strong> {{ formatNumber(run.denominator) }}</div>
                <div><strong>Status:</strong> {{ run.status }}</div>
                <div><strong>Posted:</strong> {{ run.posted_at || '—' }}</div>
            </div>

            <div v-if="run.notes" class="text-sm bg-yellow-50 p-3 rounded">{{ run.notes }}</div>

            <h3 class="text-lg font-semibold mt-4">Alokasi per Faktur Penjualan</h3>
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 border">SI Line</th>
                        <th class="px-3 py-2 border">Faktur</th>
                        <th class="px-3 py-2 border">Numerator</th>
                        <th class="px-3 py-2 border">Ratio</th>
                        <th class="px-3 py-2 border">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="alloc in run.allocations || []" :key="alloc.id" class="border-b">
                        <td class="px-3 py-2">{{ alloc.sales_invoice_line?.description || alloc.sales_invoice_line_id }}</td>
                        <td class="px-3 py-2">
                            <Link
                                v-if="alloc.sales_invoice_line?.invoice"
                                :href="route('sales-invoices.show', alloc.sales_invoice_line.invoice.id)"
                                class="text-main-500 underline"
                            >
                                {{ alloc.sales_invoice_line.invoice.invoice_number }}
                            </Link>
                        </td>
                        <td class="px-3 py-2 text-right">{{ formatNumber(alloc.allocation_numerator) }}</td>
                        <td class="px-3 py-2 text-right">{{ formatNumber((alloc.allocation_ratio || 0) * 100, 2) }}%</td>
                        <td class="px-3 py-2 text-right">{{ formatNumber(alloc.amount) }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="run.status === 'posted'" class="flex justify-end mt-4">
                <AppDangerButton @click="reverseRun">Reverse Allocation</AppDangerButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
