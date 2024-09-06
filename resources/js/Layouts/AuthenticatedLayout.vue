<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { HomeIcon } from '@heroicons/vue/24/solid';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { Cog8ToothIcon, ChevronRightIcon } from '@heroicons/vue/24/solid';

const showingNavigationDropdown = ref(false);
const showingMobileMenu = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <nav class="bg-white border-b border-gray-100">
            <div class="pl-6">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                            </Link>
                        </div>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ms-6">
                        <div class="ms-3 relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                                            {{ $page.props.auth.user.name }}

                                            <svg class="ms-2 -me-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">
                                        Log Out
                                    </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div class="-me-2 flex items-center sm:hidden">
                        <button @click="showingMobileMenu = !showingMobileMenu" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{'hidden': showingMobileMenu, 'inline-flex': !showingMobileMenu }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{'hidden': !showingMobileMenu, 'inline-flex': showingMobileMenu }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <div :class="{'block': showingMobileMenu, 'hidden': !showingMobileMenu}" class="sm:hidden">
                <div class="pt-2 pb-3 space-y-1">
                    <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                        Dashboard
                    </ResponsiveNavLink>
                    <!-- Add more mobile menu items here -->
                </div>

                <div class="pt-4 pb-1 border-t border-gray-200">
                    <div class="px-4">
                        <div class="font-medium text-base text-gray-800">{{ $page.props.auth.user.name }}</div>
                        <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                            Log Out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <div class="flex h-screen">
            <!-- Side Navigation -->
            <div class="hidden sm:block w-72 bg-transparent h-full">
                <div class="py-6 px-2">
                    <nav class="space-y-1">
                        <NavLink :href="route('dashboard')" :active="route().current('dashboard')" class="flex items-center">
                            <HomeIcon class="h-6 w-6 mr-2" />
                            <span>Dashboard</span>
                        </NavLink>
                        
                        <Disclosure v-slot="{ open }" as="div" class="mt-2">
                            <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-indigo-700 focus:outline-none">
                                <Cog8ToothIcon class="h-6 w-6 mr-2" />
                                <span>Pengaturan</span>
                                <ChevronRightIcon
                                    :class="open ? 'transform rotate-90' : ''"
                                    class="ml-auto h-4 w-4 text-gray-400"
                                />
                            </DisclosureButton>
                            <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                <NavLink :href="route('dashboard')" :active="route().current('/')" class="flex items-center">
                                    General
                                </NavLink>
                                <NavLink :href="route('dashboard')" :active="route().current('/')" class="flex items-center">
                                    Cabang
                                </NavLink>
                                <!-- Add more settings menu items as needed -->
                            </DisclosurePanel>
                        </Disclosure>
                    </nav>
                </div>
            </div>
            
            <div class="block w-full">
                <!-- Page Heading -->
                <header class="block">
                    <div class="mx-auto p-6">
                        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                            <slot name="header"></slot>
                        </h2>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto">
                    <div>
                        <div class="max-w-7xl mx-auto">
                            <div>
                                <div>
                                    <slot />
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>