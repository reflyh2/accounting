<script setup>
import { computed } from 'vue';
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: {
        type: String,
        default: '',
    },
});

const tabs = computed(() => [
    { label: 'Penerimaan', route: 'inventory.receipts.index', active: props.activeTab === 'inventory.receipts.index' },
    { label: 'Pengeluaran', route: 'inventory.shipments.index', active: props.activeTab === 'inventory.shipments.index' },
    { label: 'Penyesuaian', route: 'inventory.adjustments.index', active: props.activeTab === 'inventory.adjustments.index' },
    { label: 'Transfer', route: 'inventory.transfers.index', active: props.activeTab === 'inventory.transfers.index' },
]);
</script>

<template>
    <TabLinks :tabs="tabs" />
</template>

