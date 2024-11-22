<script setup>
import { EyeIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: {
        type: String,
        default: 'View'
    }
});
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center justify-center align-middle w-4 h-4 text-main-500 hover:text-main-700 focus:outline-none focus:ring-2 focus:ring-main-500 focus:ring-opacity-50"
        :title="title"
    >
        <EyeIcon class="h-4 w-4" />
    </button>
</template>