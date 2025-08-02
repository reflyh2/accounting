<script setup>
import { Link } from '@inertiajs/vue3';

defineEmits(['changePage']);

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    useInertia: {
        type: Boolean,
        default: true
    }
});
</script>

<template>
    <div v-if="links && links.length > 3" class="flex flex-wrap -mb-1">
        <template v-for="(link, key) in links" :key="key">
            <div v-if="link.url === null" 
                 class="mr-1 mb-1 px-3 py-2 text-xs leading-4 text-gray-400 border rounded"
                 v-html="link.label" />
            <Link v-else-if="useInertia"
                  class="mr-1 mb-1 px-3 py-2 text-xs leading-4 border rounded hover:bg-main-50 focus:border-main-500 focus:text-main-500"
                  :class="{ 'bg-main-200': link.active }"
                  :href="link.url"
                  v-html="link.label" />
            <a v-else
                  class="mr-1 mb-1 px-3 py-2 cursor-pointer text-xs leading-4 border rounded hover:bg-main-50 focus:border-main-500 focus:text-main-500"
                  :class="{ 'bg-main-200': link.active }"
                  @click="$emit('changePage', link.url)"
                  v-html="link.label" />
        </template>
    </div>
</template>