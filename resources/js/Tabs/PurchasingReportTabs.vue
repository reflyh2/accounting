<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Ringkasan', route: 'purchasing-reports.index', active: props.activeTab === 'purchasing-reports.index' },
    { label: 'Rencana Pembelian', route: 'purchasing-reports.plans', active: props.activeTab === 'purchasing-reports.plans' },
    { label: 'Purchase Order', route: 'purchasing-reports.orders', active: props.activeTab === 'purchasing-reports.orders' },
    { label: 'Penerimaan Barang', route: 'purchasing-reports.receipts', active: props.activeTab === 'purchasing-reports.receipts' },
    { label: 'Faktur Pembelian', route: 'purchasing-reports.invoices', active: props.activeTab === 'purchasing-reports.invoices' },
    { label: 'Retur Pembelian', route: 'purchasing-reports.returns', active: props.activeTab === 'purchasing-reports.returns' },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>
