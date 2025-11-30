<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Hutang/Piutang Internal', route: 'internal-debts.index', active: props.activeTab === 'internal-debts.index' ? true : false },
    { label: 'Pembayaran Internal', route: 'internal-debt-payments.index', active: props.activeTab === 'internal-debt-payments.index' ? true : false },
    { label: 'Umur Hutang/Piutang', route: 'internal-debt-aging.index', active: props.activeTab === 'internal-debt-aging.index' ? true : false },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>