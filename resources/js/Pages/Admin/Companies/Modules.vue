<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon, CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tenant: Object,
    company: Object,
    allModules: Object,
    enabledModules: Array,
});

const form = useForm({
    enabled_modules: [...props.enabledModules],
});

function isModuleEnabled(key) {
    return form.enabled_modules.includes(key);
}

function toggleModule(key) {
    if (isModuleEnabled(key)) {
        form.enabled_modules = form.enabled_modules.filter(m => m !== key);
    } else {
        form.enabled_modules.push(key);
    }
}

function selectAll() {
    form.enabled_modules = Object.keys(props.allModules);
}

function deselectAll() {
    form.enabled_modules = [];
}

function submit() {
    form.patch(route('admin.tenants.companies.modules.update', {
        tenant: props.tenant.id,
        company: props.company.id,
    }));
}

const moduleIcons = {
    sales: '🛒',
    booking: '📅',
    purchase: '🚚',
    inventory: '📦',
    accounting: '📊',
    assets: '🏢',
    manufacturing: '⚙️',
    costing: '📈',
    catalog: '🏷️',
    settings: '⚙️',
};
</script>

<template>
    <Head :title="`Modules - ${company.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.tenants.companies.index', tenant.id)" class="text-slate-400 hover:text-white transition-colors">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <span>Configure Modules - {{ company.name }}</span>
            </div>
        </template>

        <div class="max-w-4xl">
            <div class="bg-slate-800/50 backdrop-blur-sm rounded-xl border border-slate-700 p-6">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-700">
                    <div>
                        <h3 class="text-lg font-semibold text-white">{{ company.name }}</h3>
                        <p class="text-sm text-slate-400">Tenant: {{ tenant.name }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="selectAll"
                            class="px-3 py-1.5 text-sm text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors"
                        >
                            Select All
                        </button>
                        <button
                            type="button"
                            @click="deselectAll"
                            class="px-3 py-1.5 text-sm text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors"
                        >
                            Deselect All
                        </button>
                    </div>
                </div>

                <!-- Module Grid -->
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                        <div
                            v-for="(module, key) in allModules"
                            :key="key"
                            @click="toggleModule(key)"
                            class="relative cursor-pointer rounded-xl border-2 p-4 transition-all duration-200"
                            :class="isModuleEnabled(key) 
                                ? 'border-indigo-500 bg-indigo-500/10' 
                                : 'border-slate-600 bg-slate-900/30 hover:border-slate-500'"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">{{ moduleIcons[key] || '📋' }}</span>
                                    <div>
                                        <h4 class="font-medium text-white">{{ module.name }}</h4>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ module.description }}</p>
                                    </div>
                                </div>
                                <div 
                                    class="w-5 h-5 rounded-full flex items-center justify-center transition-colors"
                                    :class="isModuleEnabled(key) ? 'bg-indigo-500' : 'bg-slate-700'"
                                >
                                    <CheckIcon v-if="isModuleEnabled(key)" class="w-3 h-3 text-white" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="bg-slate-900/50 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Enabled modules:</span>
                            <span class="text-white font-medium">
                                {{ form.enabled_modules.length }} / {{ Object.keys(allModules).length }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-semibold rounded-lg shadow-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">Saving...</span>
                            <span v-else>Save Changes</span>
                        </button>
                        <Link
                            :href="route('admin.tenants.companies.index', tenant.id)"
                            class="px-6 py-3 text-slate-400 hover:text-white transition-colors"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
