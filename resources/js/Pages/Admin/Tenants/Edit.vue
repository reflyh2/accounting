<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tenant: Object,
});

const form = useForm({
    max_companies: props.tenant.max_companies ?? '',
    max_branches: props.tenant.max_branches ?? '',
    max_users: props.tenant.max_users ?? '',
});

const submit = () => {
    form.patch(route('admin.tenants.update', props.tenant.id));
};
</script>

<template>
    <Head :title="`Edit Tenant - ${tenant.name}`" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.tenants.index')" class="text-slate-400 hover:text-white transition-colors">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <span>Edit Tenant Limits</span>
            </div>
        </template>

        <div class="max-w-2xl">
            <div class="bg-slate-800/50 backdrop-blur-sm rounded-xl border border-slate-700 p-6">
                <!-- Tenant Info -->
                <div class="mb-8 pb-6 border-b border-slate-700">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                            {{ tenant.name?.charAt(0)?.toUpperCase() || '?' }}
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">{{ tenant.name }}</h3>
                            <p class="text-sm text-slate-400">{{ tenant.id }}</p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="max_companies" class="block text-sm font-medium text-slate-300 mb-2">
                            Max Companies
                        </label>
                        <input
                            id="max_companies"
                            type="number"
                            min="0"
                            v-model="form.max_companies"
                            placeholder="Unlimited"
                            class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                        />
                        <p class="mt-1 text-sm text-slate-400">Leave empty for unlimited</p>
                        <p v-if="form.errors.max_companies" class="mt-2 text-sm text-red-400">{{ form.errors.max_companies }}</p>
                    </div>

                    <div>
                        <label for="max_branches" class="block text-sm font-medium text-slate-300 mb-2">
                            Max Branches
                        </label>
                        <input
                            id="max_branches"
                            type="number"
                            min="0"
                            v-model="form.max_branches"
                            placeholder="Unlimited"
                            class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                        />
                        <p class="mt-1 text-sm text-slate-400">Leave empty for unlimited</p>
                        <p v-if="form.errors.max_branches" class="mt-2 text-sm text-red-400">{{ form.errors.max_branches }}</p>
                    </div>

                    <div>
                        <label for="max_users" class="block text-sm font-medium text-slate-300 mb-2">
                            Max Users
                        </label>
                        <input
                            id="max_users"
                            type="number"
                            min="0"
                            v-model="form.max_users"
                            placeholder="Unlimited"
                            class="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                        />
                        <p class="mt-1 text-sm text-slate-400">Leave empty for unlimited</p>
                        <p v-if="form.errors.max_users" class="mt-2 text-sm text-red-400">{{ form.errors.max_users }}</p>
                    </div>

                    <div class="flex items-center gap-4 pt-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-semibold rounded-lg shadow-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">Saving...</span>
                            <span v-else>Save Changes</span>
                        </button>
                        <Link
                            :href="route('admin.tenants.index')"
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
