<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div v-if="links && links.length > 3" class="flex flex-wrap -mb-1">
        <template v-for="(link, key) in links" :key="key">
            <div v-if="link.url === null" 
                 class="mr-1 mb-1 px-3 py-2 text-xs leading-4 text-gray-400 border rounded"
                 v-html="link.label" />
            <Link v-else
                  class="mr-1 mb-1 px-3 py-2 text-xs leading-4 border rounded hover:bg-main-50 focus:border-main-500 focus:text-main-500"
                  :class="{ 'bg-main-200': link.active }"
                  :href="link.url"
                  v-html="link.label" />
        </template>
    </div>
</template>