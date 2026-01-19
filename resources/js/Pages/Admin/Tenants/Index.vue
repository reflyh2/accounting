<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { PencilIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tenants: Array,
});

function formatLimit(value) {
    return value === null || value === undefined ? '∞' : value;
}

function getUsageColor(current, max) {
    if (max === null || max === undefined) return 'text-emerald-400';
    const percentage = (current / max) * 100;
    if (percentage >= 90) return 'text-red-400';
    if (percentage >= 75) return 'text-amber-400';
    return 'text-emerald-400';
}
</script>

<template>
    <Head title="Manage Tenants" />

    <AdminLayout>
        <template #header>
            Manage Tenants
        </template>

        <div class="bg-slate-800/50 backdrop-blur-sm rounded-xl border border-slate-700 overflow-hidden">
            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-700">
                    <thead class="bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Tenant
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Domain
                            </th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Companies
                            </th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Branches
                            </th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Users
                            </th>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Created
                            </th>
                            <th scope="col" class="px-6 py-4 text-right text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <tr v-for="tenant in tenants" :key="tenant.id" class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold">
                                            {{ tenant.name?.charAt(0)?.toUpperCase() || '?' }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-white">{{ tenant.name }}</div>
                                        <div class="text-sm text-slate-400">{{ tenant.id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-slate-300">{{ tenant.domain || '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span :class="getUsageColor(tenant.current_companies, tenant.max_companies)" class="text-sm font-medium">
                                    {{ tenant.current_companies }}
                                </span>
                                <span class="text-slate-500 mx-1">/</span>
                                <span class="text-sm text-slate-400">{{ formatLimit(tenant.max_companies) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span :class="getUsageColor(tenant.current_branches, tenant.max_branches)" class="text-sm font-medium">
                                    {{ tenant.current_branches }}
                                </span>
                                <span class="text-slate-500 mx-1">/</span>
                                <span class="text-sm text-slate-400">{{ formatLimit(tenant.max_branches) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span :class="getUsageColor(tenant.current_users, tenant.max_users)" class="text-sm font-medium">
                                    {{ tenant.current_users }}
                                </span>
                                <span class="text-slate-500 mx-1">/</span>
                                <span class="text-sm text-slate-400">{{ formatLimit(tenant.max_users) }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                                {{ tenant.created_at }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <Link
                                    :href="route('admin.tenants.edit', tenant.id)"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-colors"
                                >
                                    <PencilIcon class="w-4 h-4 mr-1" />
                                    Edit
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="tenants.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                No tenants found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
