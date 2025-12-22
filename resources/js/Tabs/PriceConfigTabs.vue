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
    { label: 'Kelompok Harga', route: 'catalog.price-lists.index', active: props.activeTab === 'catalog.price-lists.index' },
    { label: 'Target Pelanggan', route: 'catalog.price-list-targets.index', active: props.activeTab === 'catalog.price-list-targets.index' },
    { label: 'Harga', route: 'catalog.price-list-items.index', active: props.activeTab === 'catalog.price-list-items.index' },
]);
</script>

<template>
    <TabLinks :tabs="tabs" />
</template>
