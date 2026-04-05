<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Ringkasan', route: 'operational-reconciliation.index', active: props.activeTab === 'operational-reconciliation.index' },
    { label: 'Buku Besar', route: 'general-ledger.index', active: props.activeTab === 'general-ledger.index' },
    { label: 'Laporan Jurnal', route: 'journal-report.index', active: props.activeTab === 'journal-report.index' },
    { label: 'Buku Kas & Bank', route: 'cash-bank-book.index', active: props.activeTab === 'cash-bank-book.index' },
    { label: 'Neraca Saldo', route: 'trial-balance.index', active: props.activeTab === 'trial-balance.index' },
    { label: 'Laba/Rugi', route: 'income.index', active: props.activeTab === 'income.index' },
    { label: 'Neraca', route: 'balance-sheet.index', active: props.activeTab === 'balance-sheet.index' },
    { label: 'Arus Kas', route: 'cash-flow.index', active: props.activeTab === 'cash-flow.index' },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>