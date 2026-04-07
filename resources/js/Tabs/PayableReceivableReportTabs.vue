<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Ringkasan', route: 'payable-receivable-overview.index', active: props.activeTab === 'payable-receivable-overview.index' },
    { label: 'Umur Hutang', route: 'external-payable-aging.index', active: props.activeTab === 'external-payable-aging.index' },
    { label: 'Mutasi Hutang', route: 'external-payable-mutation.index', active: props.activeTab === 'external-payable-mutation.index' },
    { label: 'Kartu Hutang', route: 'external-payable-card.index', active: props.activeTab === 'external-payable-card.index' },
    { label: 'Umur Piutang', route: 'external-receivable-aging.index', active: props.activeTab === 'external-receivable-aging.index' },
    { label: 'Mutasi Piutang', route: 'external-receivable-mutation.index', active: props.activeTab === 'external-receivable-mutation.index' },
    { label: 'Kartu Piutang', route: 'external-receivable-card.index', active: props.activeTab === 'external-receivable-card.index' },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>
