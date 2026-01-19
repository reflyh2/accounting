<template>
    <!-- FinsysPro Typography Logo with Icon -->
    <svg :class="iconOnly ? 'h-10 w-10' : ''" viewBox="0 0 130 40" xmlns="http://www.w3.org/2000/svg" v-if="!iconOnly">
        <defs>
            <linearGradient id="brandGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#1e40af" />
                <stop offset="100%" style="stop-color:#4f46e5" />
            </linearGradient>
        </defs>
        <!-- F Icon Mark -->
        <g transform="translate(0, 4)">
            <path d="M4 0h18a4 4 0 0 1 4 4v24a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z" fill="url(#brandGradient)"/>
            <path d="M8 8h12v3H11v4h7v3h-7v8H8V8z" fill="white"/>
        </g>
        <!-- FinsysPro Text -->
        <text x="34" y="28" font-family="Inter, system-ui, sans-serif" font-size="20" font-weight="700" fill="url(#brandGradient)">
            <tspan>Finsys</tspan><tspan font-weight="500" fill="#1e40af">Pro</tspan>
        </text>
    </svg>
    
    <!-- Icon Only Version -->
    <svg class="h-10 w-10" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" v-else>
        <defs>
            <linearGradient id="iconGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#1e40af" />
                <stop offset="100%" style="stop-color:#4f46e5" />
            </linearGradient>
        </defs>
        <path d="M4 0h24a4 4 0 0 1 4 4v24a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z" fill="url(#iconGradient)"/>
        <path d="M8 6h16v4H12v5h10v4H12v9H8V6z" fill="white"/>
    </svg>
</template>

<script setup>
defineProps({
    iconOnly: {
        type: Boolean,
        default: false
    }
});
</script>
