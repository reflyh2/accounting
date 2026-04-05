<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Ringkasan', route: 'inventory-reports.index', active: props.activeTab === 'inventory-reports.index' },
    { label: 'Mutasi Stok', route: 'inventory-reports.stock-movement', active: props.activeTab === 'inventory-reports.stock-movement' },
    { label: 'Valuasi Stok', route: 'inventory-reports.stock-valuation', active: props.activeTab === 'inventory-reports.stock-valuation' },
    { label: 'Kartu Stok', route: 'inventory-reports.stock-card', active: props.activeTab === 'inventory-reports.stock-card' },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>
