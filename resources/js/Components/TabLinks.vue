<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
        validator: (value) => {
            return value.every(tab => 
                typeof tab.label === 'string' && 
                typeof tab.route === 'string' &&
                typeof tab.active === 'boolean'
            );
        }
    }
});
</script>

<template>
    <div class="flex">
        <template v-for="tab in tabs" :key="tab.route">
            <Link
                v-if="!tab.active"
                :href="route(tab.route)"
                class="px-4 py-3 text-sm rounded-t hover:text-main-700"
            >
                {{ tab.label }}
            </Link>
            <div
                v-else
                class="px-4 py-3 rounded-t text-sm font-bold bg-white text-main-700 border-x border-t border-gray-200 -mb-px"
            >
                {{ tab.label }}
            </div>
        </template>
    </div>
</template>