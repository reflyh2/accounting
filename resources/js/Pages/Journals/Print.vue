<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, computed } from 'vue';
import { formatNumber, terbilang } from '@/utils/numberFormat';

const page = usePage();

const hasOtherCurrency = computed(() => {
    return props.journal?.journal_entries?.some(entry => entry.currency_id != page.props.primaryCurrency.id);
});

const props = defineProps({
    journal: Object,
    primaryCurrency: Object,
});

onMounted(() => {
    window.print();
});
</script>

<template>
    <Head title="Print Journal" />

    <div class="print-layout">
        <h1>Jurnal</h1>
        <h3>{{ journal.journal_number }}</h3>
        <div class="journal-details grid grid-cols-2 gap-1">
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">Tanggal:</span>
                <span>{{ journal.date }}</span>
            </div>
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">Perusahaan:</span>
                <span>{{ journal.branch.branch_group.company.name }}</span>
            </div>
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">Cabang:</span>
                <span>{{ journal.branch.name }}</span>
            </div>
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">No. Referensi:</span>
                <span>{{ journal.reference_number }}</span>
            </div>
        </div>

        <table class="journal-entries">
            <thead>
                <tr>
                    <th class="text-left">No. Akun</th>
                    <th class="text-left">Akun</th>
                    <th class="text-left" v-if="hasOtherCurrency">Mata Uang</th>
                    <th class="text-right">Debet</th>
                    <th class="text-right">Kredit</th>
                    <th class="text-right" v-if="hasOtherCurrency">Kurs</th>
                    <th class="text-right" v-if="hasOtherCurrency">Debit ({{ page.props.primaryCurrency.code }})</th>
                    <th class="text-right" v-if="hasOtherCurrency">Kredit ({{ page.props.primaryCurrency.code }})</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in journal.journal_entries" :key="entry.id">
                    <td>{{ entry.account.code }}</td>
                    <td>{{ entry.account.name }}</td>
                    <td class="text-right" v-if="hasOtherCurrency">{{ entry.currency.code }}</td>
                    <td class="text-right">{{ formatNumber(entry.debit) }}</td>
                    <td class="text-right">{{ formatNumber(entry.credit) }}</td>
                    <td class="text-right" v-if="hasOtherCurrency">{{ formatNumber(entry.exchange_rate) }}</td>
                    <td class="text-right" v-if="hasOtherCurrency">{{ formatNumber(entry.primary_currency_debit) }}</td>
                    <td class="text-right" v-if="hasOtherCurrency">{{ formatNumber(entry.primary_currency_credit) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-left">Total</th>
                    <th v-if="hasOtherCurrency"></th>
                    <th v-if="hasOtherCurrency"></th>
                    <th v-if="hasOtherCurrency"></th>
                    <th v-if="hasOtherCurrency"></th>
                    <th class="text-right">{{ formatNumber(journal.journal_entries.reduce((sum, entry) => sum + Number(entry.primary_currency_debit), 0)) }}</th>
                    <th class="text-right">{{ formatNumber(journal.journal_entries.reduce((sum, entry) => sum + Number(entry.primary_currency_credit), 0)) }}</th>
                </tr>
            </tfoot>
        </table>

        <div class="grid grid-cols-4 gap-4 mt-8">
            <div class="col-span-2">
               <h3 class="font-semibold border-b border-gray-600 pb-2">Catatan</h3>
               <div class="first-letter:uppercase">{{ journal.description }}</div>  
            </div>
            <div class="text-center">
               <h3 class="font-semibold border-b border-gray-600 pb-2">Dibuat Oleh</h3>
               <div class="pt-16 border-b border-gray-600 pb-2">{{ journal.user.name }}</div>
            </div>
            <div class="text-center">
               <h3 class="font-semibold border-b border-gray-600 pb-2">Disetujui Oleh</h3>
               <div class="pt-16 border-b border-gray-600 pb-2">&nbsp;</div>
            </div>

        </div>
    </div>
</template>

<style scoped>
@media print {
    .print-layout {
        font-family: Arial, sans-serif;
        font-size: 10pt;
        line-height: 1.25;
    }

    h1 {
        font-size: 16pt;
    }
    h3 {
        font-size: 10pt;
        margin-bottom: 16px;
    }

    .journal-details {
        margin-bottom: 16px;
    }

    .journal-details p {
        margin: 5px 0;
    }

    .journal-entries {
        width: 100%;
        border-collapse: collapse;
    }

    .journal-entries th {
      border-top: 1px solid #000;
      border-bottom: 1px solid #000;
    }

    .journal-entries th,
    .journal-entries td {
        padding: 4px 8px;
    }

    .journal-entries th {
        background-color: #f0f0f0;
    }

    .text-right {
        text-align: right;
    }

    @page {
        size: A4;
        margin: 0.5cm 1cm;
    }
}
</style>