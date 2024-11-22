<script setup>
defineProps({
    title: String,
    subtitle: String,
    dateRange: String,
    companies: Array,
    branches: Array,
});
</script>

<template>
    <div class="print-layout">
        <div class="print-header">
            <h1 class="text-xl font-bold">{{ title }}</h1>
            <h2 class="text-lg">{{ subtitle }}</h2>
            <p class="text-sm">{{ dateRange }}</p>
            <div class="text-sm mt-2">
                <p v-if="companies?.length">
                    Perusahaan: {{ companies.map(c => c.name).join(', ') }}
                </p>
                <p v-if="branches?.length">
                    Cabang: {{ branches.map(b => b.name).join(', ') }}
                </p>
            </div>
        </div>
        <slot></slot>
    </div>
</template>

<style>
@media print {
    .print-layout {
        padding: 20px;
    }

    .print-header {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    @page {
        size: landscape;
        margin: 1cm;
    }

    * {
        overflow: visible !important;
    }
}

.print-only {
    display: none;
}

@media print {
    .print-only {
        display: block;
    }
    .no-print {
        display: none !important;
    }
}
</style>