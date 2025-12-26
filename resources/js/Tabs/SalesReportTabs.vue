<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Ringkasan', route: 'sales-reports.index', active: props.activeTab === 'sales-reports.index' },
    { label: 'Sales Order', route: 'sales-reports.orders', active: props.activeTab === 'sales-reports.orders' },
    { label: 'Pengiriman Barang', route: 'sales-reports.deliveries', active: props.activeTab === 'sales-reports.deliveries' },
    { label: 'Faktur Penjualan', route: 'sales-reports.invoices', active: props.activeTab === 'sales-reports.invoices' },
    { label: 'Retur Penjualan', route: 'sales-reports.returns', active: props.activeTab === 'sales-reports.returns' },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>
