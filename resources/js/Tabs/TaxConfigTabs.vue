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
    { label: 'Yurisdiksi Pajak', route: 'tax-jurisdictions.index', active: props.activeTab === 'tax-jurisdictions.index' },
    { label: 'Komponen Pajak', route: 'tax-components.index', active: props.activeTab === 'tax-components.index' },
    { label: 'Kategori Pajak', route: 'tax-categories.index', active: props.activeTab === 'tax-categories.index' },
    { label: 'Aturan Pajak', route: 'tax-rules.index', active: props.activeTab === 'tax-rules.index' },
]);
</script>

<template>
    <TabLinks :tabs="tabs" />
</template>

