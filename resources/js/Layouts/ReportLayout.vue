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
    <div class="p-4">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">{{ title }}</h1>
            <h2 class="text-lg">{{ subtitle }}</h2>
            <p class="text-sm">{{ dateRange }}</p>
            <div class="text-sm mt-2">
                <p v-if="companies.length || !branches.length">
                    Perusahaan: {{ companies.length ? companies.map(c => c.name).join(', ') : (branches.length ? '' : 'Semua Perusahaan') }}
                </p>
                <p>
                    Cabang: {{ branches.length ? branches.map(b => b.name).join(', ') : 'Semua Cabang' }}
                </p>
            </div>
        </div>
        <slot></slot>
    </div>
</template>