<script setup>
import TabLinks from '@/Components/TabLinks.vue';

const props = defineProps({
    activeTab: String,
});

const tabs = [
    { label: 'Barang', route: 'catalog.goods.index', active: props.activeTab === 'catalog.goods.index' ? true : false },
    { label: 'Jasa', route: 'catalog.services.index', active: props.activeTab === 'catalog.services.index' ? true : false },
    { label: 'Akomodasi', route: 'catalog.accommodation.index', active: props.activeTab === 'catalog.accommodation.index' ? true : false },
    { label: 'Rental', route: 'catalog.rental.index', active: props.activeTab === 'catalog.rental.index' ? true : false },
    { label: 'Paket', route: 'catalog.packages.index', active: props.activeTab === 'catalog.packages.index' ? true : false },
];

</script>

<template>
    <TabLinks :tabs="tabs" />
</template>