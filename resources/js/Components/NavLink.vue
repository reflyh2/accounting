<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block py-2 px-2 text-sm font-medium text-indigo-700 bg-indigo-200 rounded-sm'
        : 'block py-2 px-2 text-sm font-medium text-gray-900 hover:text-indigo-700 rounded-sm'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
