<script setup>
import { ref } from 'vue';

defineProps({
    text: {
        type: String,
        required: true
    }
});

const showTooltip = ref(false);
</script>

<template>
    <div class="relative inline-block ml-1">
        <button
            type="button"
            @mouseenter="showTooltip = true"
            @mouseleave="showTooltip = false"
            @focus="showTooltip = true"
            @blur="showTooltip = false"
            class="text-gray-400 hover:text-gray-500 focus:outline-none"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
        </button>
        
        <div
            v-show="showTooltip"
            class="absolute z-[9999] w-64 px-2 py-1 text-sm text-gray-500 bg-white rounded border shadow-lg"
            :class="{
                'bottom-full left-1/2 -translate-x-1/2 mb-1': true
            }"
        >
            {{ text }}
        </div>
    </div>
</template> 