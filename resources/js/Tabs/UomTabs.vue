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
    { label: 'Satuan', route: 'uoms.index', active: props.activeTab === 'uoms.index' },
    { label: 'Konversi Satuan', route: 'uom-conversions.index', active: props.activeTab === 'uom-conversions.index' },
]);
</script>

<template>
    <TabLinks :tabs="tabs" />
</template>
