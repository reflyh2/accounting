<script setup>
import { ref, nextTick, watch, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { BanknotesIcon, HomeIcon } from '@heroicons/vue/24/solid';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { Cog8ToothIcon, ChevronRightIcon } from '@heroicons/vue/24/solid';
import AlertNotification from '@/Components/AlertNotification.vue';

const showingNavigationDropdown = ref(false);
const showingMobileMenu = ref(false);

const page = usePage();
const alert = ref(null);

function checkFlashMessages() {
    const flash = page.props.flash;
    if (flash) {
        if (flash.success) {
            alert.value = { type: 'success', message: flash.success };
        } else if (flash.warning) {
            alert.value = { type: 'warning', message: flash.warning };
        } else if (flash.error) {
            alert.value = { type: 'error', message: flash.error };
        } else {
            alert.value = null;
            return;
        }
    } else {
        alert.value = null;
    }
}

onMounted(checkFlashMessages);

// Watch for changes in flash messages
watch(() => page.props.flash, (newFlash, oldFlash) => {
    // Only update if the flash message has actually changed
    if (JSON.stringify(newFlash) !== JSON.stringify(oldFlash)) {
        nextTick(checkFlashMessages);
    }
}, { deep: true });

// Add this computed property to check if any settings route is active
const isSettingsActive = computed(() => {
    return route().current('companies.*') 
        || route().current('branches.*') 
        || route().current('branch-groups.*')
        || route().current('roles.*')
        || route().current('users.*')
        || route().current('suppliers.*')
        || route().current('customers.*')
        || route().current('employees.*')
        || route().current('partners.*')
        || route().current('members.*');
});

const isAccountingActive = computed(() => {
    return route().current('accounts.*')
        || route().current('currencies.*')
        || route().current('journals.*')
        || route().current('cash-receipt-journals.*')
        || route().current('cash-payment-journals.*')
        || route().current('assets.*')
        || route().current('asset-categories.*')
        || route().current('asset-maintenance.*')
        || route().current('general-ledger.*')
        || route().current('cash-bank-book.*')
        || route().current('income.*')
        || route().current('balance-sheet.*');
});
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex flex-col">
        <nav class="bg-white border-b border-gray-100 no-print">
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
            <div :class="{'block': showingMobileMenu, 'hidden': !showingMobileMenu}" class="sm:hidden bg-white fixed inset-0 top-16 z-50">
                <div class="pt-2 pb-3 space-y-1 bg-white">
                    <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')" class="flex items-center ps-3">
                        <HomeIcon class="h-5 w-5 mr-2" />
                        Dashboard
                    </ResponsiveNavLink>

                    <!-- Accounting Section -->
                    <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isAccountingActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <BanknotesIcon class="h-5 w-5 mr-2" />
                            <span>Akuntansi & Keuangan</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink :href="route('accounts.index')" :active="route().current('accounts.*')" class="pl-11">
                                Bagan Akun
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('currencies.index')" :active="route().current('currencies.*')" class="pl-11">
                                Mata Uang
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('journals.index')" 
                                :active="route().current('journals.*') || route().current('cash-receipt-journals.*') || route().current('cash-payment-journals.*')" 
                                class="pl-11"
                            >
                                Jurnal
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('assets.index')" 
                                :active="route().current('assets.*') || route().current('asset-categories.*') || route().current('asset-maintenance.*')" 
                                class="pl-11"
                            >
                                Aset
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('general-ledger.index')" 
                                :active="
                                    route().current('general-ledger.*') 
                                    || route().current('cash-bank-book.*') 
                                    || route().current('income.*') 
                                    || route().current('balance-sheet.*')
                                " 
                                class="pl-11"
                            >
                                Laporan Akuntansi
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Settings Section -->
                    <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isSettingsActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <Cog8ToothIcon class="h-5 w-5 mr-2" />
                            <span>Pengaturan</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('/')" class="pl-11">
                                General
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('companies.index')" 
                                :active="route().current('companies.*') || route().current('branches.*') || route().current('branch-groups.*')" 
                                class="pl-11"
                            >
                                Perusahaan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('suppliers.index')" 
                                :active="
                                    route().current('suppliers.*')
                                    || route().current('customers.*')
                                    || route().current('employees.*')
                                    || route().current('partners.*')
                                    || route().current('members.*')
                                " 
                                class="pl-11"
                            >
                                Relasi Bisnis
                            </ResponsiveNavLink>
                            <ResponsiveNavLink 
                                :href="route('roles.index')" 
                                :active="
                                    route().current('roles.*')
                                    || route().current('users.*')
                                " 
                                class="pl-11"
                            >
                                Hak Akses Pengguna
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>
                </div>

                <!-- Existing profile section -->
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

        <div class="flex flex-1 overflow-hidden">
            <!-- Side Navigation -->
            <div class="hidden sm:block w-72 flex-shrink-0 overflow-y-auto thin-scrollbar fixed top-16 bottom-0 left-0 no-print">
                <div class="py-6 px-2">
                    <nav class="space-y-1">
                        <NavLink :href="route('dashboard')" :active="route().current('dashboard')" class="flex items-center">
                            <HomeIcon class="h-6 w-6 mr-2" />
                            <span>Dashboard</span>
                        </NavLink>

                        <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isAccountingActive">
                            <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                <BanknotesIcon class="h-6 w-6 mr-2" />
                                <span>Akuntansi & Keuangan</span>
                                <ChevronRightIcon
                                    :class="open ? 'transform rotate-90' : ''"
                                    class="ml-auto h-4 w-4 text-gray-400"
                                />
                            </DisclosureButton>
                            <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                <NavLink :href="route('accounts.index')" 
                                    :active="route().current('accounts.*')" 
                                    class="flex items-center"
                                >
                                    Bagan Akun
                                </NavLink>
                                <NavLink :href="route('currencies.index')"
                                    :active="route().current('currencies.*')" 
                                    class="flex items-center"
                                >
                                    Mata Uang
                                </NavLink>
                                <NavLink :href="route('journals.index')" 
                                    :active="
                                        route().current('journals.*') 
                                        || route().current('cash-receipt-journals.*') 
                                        || route().current('cash-payment-journals.*')
                                    " 
                                    class="flex items-center"
                                >
                                    Jurnal
                                </NavLink>                                
                                <NavLink :href="route('assets.index')" 
                                    :active="
                                        route().current('assets.*') 
                                        || route().current('asset-categories.*') 
                                        || route().current('asset-maintenance.*')
                                    " 
                                    class="flex items-center"
                                >
                                    Aset
                                </NavLink>
                                <NavLink 
                                    :href="route('general-ledger.index')" 
                                    :active="
                                        route().current('general-ledger.*') 
                                        || route().current('cash-bank-book.*') 
                                        || route().current('income.*')
                                        || route().current('balance-sheet.*')
                                    " 
                                    class="flex items-center"
                                >
                                    Laporan Akuntansi
                                </NavLink>
                                <!-- Add more settings menu items as needed -->
                            </DisclosurePanel>
                        </Disclosure>
                        
                        <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isSettingsActive">
                            <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
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
                                <NavLink 
                                    :href="route('companies.index')" 
                                    :active="
                                        route().current('companies.*') 
                                        || route().current('branches.*') 
                                        || route().current('branch-groups.*')
                                    " 
                                    class="flex items-center"
                                >
                                    Perusahaan
                                </NavLink>                                
                                <NavLink
                                    :href="route('suppliers.index')" 
                                    :active="
                                        route().current('suppliers.*')
                                        || route().current('customers.*')
                                        || route().current('employees.*')
                                        || route().current('partners.*')
                                        || route().current('members.*')
                                    " 
                                    class="flex items-center"
                                >
                                    Relasi Bisnis
                                </NavLink>
                                <NavLink 
                                    :href="route('roles.index')" 
                                    :active="
                                        route().current('roles.*')
                                        || route().current('users.*')
                                    " 
                                    class="flex items-center"
                                >
                                    Hak Akses Pengguna
                                </NavLink>
                                <!-- Add more settings menu items as needed -->
                            </DisclosurePanel>
                        </Disclosure>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="flex-1 overflow-y-auto thin-scrollbar fixed top-16 bottom-0 right-0 left-0 sm:left-72 main-content print:p-0 print:m-0 print:bg-white print:left-0 print:w-full print:h-full z-0">
                <!-- Page Heading -->
                <header class="bg-gray-100 no-print">
                    <div class="min-w-min md:min-w-max mx-auto py-6 pl-6 sm:pl-0">
                        <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                            <slot name="header"></slot>
                        </h2>
                    </div>
                </header>

                <AlertNotification 
                    v-if="alert"
                    :type="alert.type"
                    :message="alert.message"
                    @close="alert = null" 
                />

                <!-- Page Content -->
                <main>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>