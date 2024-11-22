<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon } from '@heroicons/vue/24/solid';

defineProps({
    href: {
        type: String,
        required: true
    },
    text: {
        type: String,
        default: 'Back'
    }
});
</script>

<template>
    <Link :href="href" class="inline-flex items-center text-sm text-main-600 hover:text-main-800 hover:underline">
        <ChevronLeftIcon class="w-5 h-5 mr-1" />
        {{ text }}
    </Link>
</template>