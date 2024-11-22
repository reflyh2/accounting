<script setup>
import { onMounted } from 'vue';
import { formatNumber, terbilang } from '@/utils/numberFormat';

const props = defineProps({
    cashReceiptJournal: Object,
});

const kasBankEntry = props.cashReceiptJournal.journal_entries.find(entry => entry.debit != 0);
const otherEntries = props.cashReceiptJournal.journal_entries.filter(entry => entry.debit == 0);

onMounted(() => {
    window.print();
});
</script>

<template>
    <div class="print-layout">
        <h1>Penerimaan Kas</h1>
        <h3>{{ cashReceiptJournal.journal_number }}</h3>
        <div class="journal-details grid grid-cols-2 gap-1">
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">Tanggal:</span>
                <span>{{ cashReceiptJournal.date }}</span>
            </div>
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">Perusahaan:</span>
                <span>{{ cashReceiptJournal.branch.branch_group.company.name }}</span>
            </div>
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">Cabang:</span>
                <span>{{ cashReceiptJournal.branch.name }}</span>
            </div>
            <div class="flex py-0 my-0.5">
                <span class="w-32 font-bold">No. Referensi:</span>
                <span>{{ cashReceiptJournal.reference_number }}</span>
            </div>
            <div class="flex py-0 my-0.5 col-span-2">
                <span class="w-32 font-bold">Terima ke Akun:</span>
                <span>{{ kasBankEntry.account.code }} - {{ kasBankEntry.account.name }}</span>
            </div>
        </div>

        <table class="journal-entries">
            <thead>
                <tr>
                  <th class="text-left">No. Akun</th>
                  <th class="text-left">Akun</th>
                  <th class="text-right">Jumlah</th>
                  <th class="text-left">Mata Uang</th>
                  <th class="text-right">Kurs</th>
                  <th class="text-right">Jumlah Mata Uang Utama</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in otherEntries" :key="entry.id">
                    <td>{{ entry.account.code }}</td>
                    <td>{{ entry.account.name }}</td>
                    <td class="text-right">{{ formatNumber(entry.credit) }}</td>
                    <td>{{ entry.currency.code }}</td>
                    <td class="text-right">{{ formatNumber(entry.exchange_rate) }}</td>
                    <td class="text-right">{{ formatNumber(entry.primary_currency_credit) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-left">Total</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th class="text-right">{{ formatNumber(cashReceiptJournal.journal_entries.reduce((sum, entry) => sum + (Number(entry.credit) * Number(entry.exchange_rate)), 0)) }}</th>
                </tr>

                <tr>
                    <th colspan="2" class="text-left">Total Penerimaan ke {{ kasBankEntry.account.name }}</th>
                    <th class="text-right">{{ formatNumber(kasBankEntry.debit) }}</th>
                    <th class="text-left">{{ kasBankEntry.currency.code }}</th>
                    <th></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <div class="grid grid-cols-4 gap-4 mt-8">
            <div class="col-span-2">
                <h3 class="font-semibold border-b border-gray-600 pb-2">Catatan</h3>
                <div class="first-letter:uppercase">{{ cashReceiptJournal.description }}</div>
            </div>
            <div class="text-center">
                <h3 class="font-semibold border-b border-gray-600 pb-2">Dibuat Oleh</h3>
                <div class="pt-16 border-b border-gray-600 pb-2">{{ cashReceiptJournal.user.name }}</div>
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