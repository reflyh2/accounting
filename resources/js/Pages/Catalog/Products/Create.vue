<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    group: String,
    groupLabel: String,
    kindGroups: Object,
    templates: Array,
});

const groupTabs = computed(() => {
    return Object.entries(props.kindGroups || {}).map(([key, group]) => ({
        key,
        label: group.label,
        route: route('catalog.products.create', { group: key }),
        active: props.group === key,
    }));
});
</script>

<template>
    <Head :title="`Buat Produk - ${groupLabel}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>Buat Produk: {{ groupLabel }}</h2>
                <Link
                    :href="route('catalog.products.index', { group })"
                    class="text-sm text-gray-600 hover:text-gray-900"
                >
                    &larr; Kembali ke Daftar
                </Link>
            </div>
        </template>

        <div class="mx-auto">
            <!-- Group Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="flex space-x-4" aria-label="Tabs">
                    <Link
                        v-for="tab in groupTabs"
                        :key="tab.key"
                        :href="tab.route"
                        :class="[
                            tab.active
                                ? 'border-indigo-500 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm'
                        ]"
                    >
                        {{ tab.label }}
                    </Link>
                </nav>
            </div>

            <!-- Template Picker -->
            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Pilih Jenis Produk</h3>
                <p class="text-sm text-gray-600 mb-6">
                    Pilih template produk yang sesuai dengan jenis produk yang akan Anda buat.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="template in templates"
                        :key="template.template_code"
                        :href="route('catalog.products.create-with-template', { templateCode: template.template_code })"
                        class="group relative bg-white border border-gray-200 rounded-lg p-6 hover:border-indigo-500 hover:shadow-md transition-all duration-200"
                    >
                        <div class="flex flex-col">
                            <h4 class="text-base font-semibold text-gray-900 group-hover:text-indigo-600">
                                {{ template.label }}
                            </h4>
                            <p class="mt-2 text-sm text-gray-500">
                                {{ template.description }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    v-for="cap in template.capabilities"
                                    :key="cap"
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800"
                                >
                                    {{ formatCapability(cap) }}
                                </span>
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800"
                                >
                                    {{ formatCostModel(template.cost_model) }}
                                </span>
                            </div>
                        </div>
                        <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </Link>
                </div>

                <div v-if="!templates || templates.length === 0" class="text-center py-12 text-gray-500">
                    Tidak ada template tersedia untuk grup ini.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script>
export default {
    methods: {
        formatCapability(cap) {
            const labels = {
                'inventory_tracked': 'Inventori',
                'variantable': 'Varian',
                'bookable': 'Booking',
                'rental': 'Rental',
                'serialized': 'Serial',
                'package': 'Paket',
            };
            return labels[cap] || cap;
        },
        formatCostModel(model) {
            const labels = {
                'inventory_layer': 'Inventory Layer',
                'direct_expense_per_sale': 'Direct Expense',
                'job_costing': 'Job Costing',
                'asset_usage_costing': 'Asset Usage',
                'prepaid_consumption': 'Prepaid',
                'hybrid': 'Hybrid',
                'none': 'None',
            };
            return labels[model] || model;
        },
    },
};
</script>
