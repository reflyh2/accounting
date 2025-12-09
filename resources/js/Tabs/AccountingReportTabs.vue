<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Buku Besar', route: 'general-ledger.index', active: props.activeTab === 'general-ledger.index' ? true : false },
    { label: 'Buku Kas & Bank', route: 'cash-bank-book.index', active: props.activeTab === 'cash-bank-book.index' ? true : false },
    { label: 'Laba/Rugi', route: 'income.index', active: props.activeTab === 'income.index' ? true : false },
    { label: 'Neraca', route: 'balance-sheet.index', active: props.activeTab === 'balance-sheet.index' ? true : false },
    { label: 'Umur Hutang', route: 'external-payable-aging.index', active: props.activeTab === 'external-payable-aging.index' ? true : false },
    { label: 'Mutasi Hutang', route: 'external-payable-mutation.index', active: props.activeTab === 'external-payable-mutation.index' ? true : false },
    { label: 'Kartu Hutang', route: 'external-payable-card.index', active: props.activeTab === 'external-payable-card.index' ? true : false },
    { label: 'Umur Piutang', route: 'external-receivable-aging.index', active: props.activeTab === 'external-receivable-aging.index' ? true : false },
    { label: 'Mutasi Piutang', route: 'external-receivable-mutation.index', active: props.activeTab === 'external-receivable-mutation.index' ? true : false },
    { label: 'Kartu Piutang', route: 'external-receivable-card.index', active: props.activeTab === 'external-receivable-card.index' ? true : false },
    { label: 'Rekonsiliasi Operasional', route: 'operational-reconciliation.index', active: props.activeTab === 'operational-reconciliation.index' ? true : false },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>