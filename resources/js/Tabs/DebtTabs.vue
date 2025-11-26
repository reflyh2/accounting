<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Hutang', route: 'external-payables.index', active: props.activeTab === 'external-payables.index' ? true : false },
    { label: 'Piutang', route: 'external-receivables.index', active: props.activeTab === 'external-receivables.index' ? true : false },
    { label: 'Pembayaran Hutang', route: 'external-payable-payments.index', active: props.activeTab === 'external-payable-payments.index' ? true : false },
    { label: 'Penerimaan Piutang', route: 'external-receivable-payments.index', active: props.activeTab === 'external-receivable-payments.index' ? true : false },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>