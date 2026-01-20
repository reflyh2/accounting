<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon, Cog6ToothIcon, BuildingOffice2Icon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tenant: Object,
    companies: Array,
    totalModules: Number,
});

function getModulesBadgeColor(count, total) {
    if (count === total) return 'bg-emerald-500/20 text-emerald-400';
    if (count === 0) return 'bg-red-500/20 text-red-400';
    return 'bg-amber-500/20 text-amber-400';
}
</script>

<template>
    <Head :title="`Companies - ${tenant.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.tenants.index')" class="text-slate-400 hover:text-white transition-colors">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <span>Companies - {{ tenant.name }}</span>
            </div>
        </template>

        <div class="bg-slate-800/50 backdrop-blur-sm rounded-xl border border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-700">
                    <thead class="bg-slate-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Company
                            </th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Branches
                            </th>
                            <th scope="col" class="px-6 py-4 text-center text-xs font-medium text-slate-400 uppercase tracking-wider">
                                Modules
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
                        <tr v-for="company in companies" :key="company.id" class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center">
                                            <BuildingOffice2Icon class="w-5 h-5 text-white" />
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-white">{{ company.name }}</div>
                                        <div class="text-sm text-slate-400">{{ company.legal_name || '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="text-sm text-slate-300">{{ company.branches_count }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="getModulesBadgeColor(company.modules_count, totalModules)"
                                >
                                    {{ company.modules_count }} / {{ totalModules }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                                {{ company.created_at }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <Link
                                    :href="route('admin.tenants.companies.modules', { tenant: tenant.id, company: company.id })"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-400 hover:text-indigo-300 hover:bg-indigo-500/10 rounded-lg transition-colors"
                                >
                                    <Cog6ToothIcon class="w-4 h-4 mr-1" />
                                    Modules
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="companies.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                No companies found for this tenant.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
