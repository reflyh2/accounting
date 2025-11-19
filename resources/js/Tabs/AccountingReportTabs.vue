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
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>