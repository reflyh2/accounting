<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const showingNavigationDropdown = ref(false);
const page = usePage();
</script>

<template>
    <div>
        <div class="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800">
            <nav class="bg-slate-900/80 backdrop-blur-sm border-b border-slate-700">
                <div class="mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('admin.tenants.index')" class="flex items-center gap-3">
                                    <ApplicationLogo class="block h-10 w-auto fill-current text-indigo-400" />
                                    <span class="text-white font-semibold text-lg">Admin Dashboard</span>
                                </Link>
                            </div>
                            
                            <!-- Navigation Links -->
                            <div class="hidden sm:flex sm:ml-10 space-x-4">
                                <Link
                                    :href="route('admin.tenants.index')"
                                    class="px-3 py-2 rounded-md text-sm font-medium transition-colors"
                                    :class="route().current('admin.tenants.*') 
                                        ? 'bg-indigo-600 text-white' 
                                        : 'text-slate-300 hover:bg-slate-700 hover:text-white'"
                                >
                                    Tenants
                                </Link>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <!-- Settings Dropdown -->
                            <div class="ml-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-slate-300 hover:text-white focus:outline-none transition ease-in-out duration-150">
                                                {{ page.props.auth?.admin?.name || 'Admin' }}

                                                <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('admin.logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-mr-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown" class="inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-white hover:bg-slate-700 focus:outline-none transition duration-150 ease-in-out">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path :class="{'hidden': showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path :class="{'hidden': !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <Link
                            :href="route('admin.tenants.index')"
                            class="block pl-3 pr-4 py-2 text-base font-medium border-l-4"
                            :class="route().current('admin.tenants.*')
                                ? 'border-indigo-400 text-indigo-400 bg-slate-800'
                                : 'border-transparent text-slate-300 hover:text-white hover:bg-slate-700'"
                        >
                            Tenants
                        </Link>
                    </div>

                    <div class="pt-4 pb-1 border-t border-slate-700">
                        <div class="px-4">
                            <div class="font-medium text-base text-white">{{ page.props.auth?.admin?.name || 'Admin' }}</div>
                            <div class="font-medium text-sm text-slate-400">{{ page.props.auth?.admin?.email || '' }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <Link :href="route('admin.logout')" method="post" as="button" class="block w-full text-left pl-3 pr-4 py-2 text-base font-medium text-slate-300 hover:text-white hover:bg-slate-700">
                                Log Out
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-slate-800/50 backdrop-blur-sm shadow border-b border-slate-700" v-if="$slots.header">
                <div class="flex justify-between items-center mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <h2 class="font-bold text-xl text-white leading-tight">
                        <slot name="header" />
                    </h2>
                    <slot name="header-right" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="py-8">
                <div class="mx-auto sm:px-6 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
