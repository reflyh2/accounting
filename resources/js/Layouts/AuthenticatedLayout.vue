<script setup>
import { ref, nextTick, watch, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { BanknotesIcon, HomeIcon, BuildingOffice2Icon } from '@heroicons/vue/24/solid';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { Cog8ToothIcon, ChevronRightIcon, Bars3Icon } from '@heroicons/vue/24/solid';
import AlertNotification from '@/Components/AlertNotification.vue';

const showingNavigationDropdown = ref(false);
const showingMobileMenu = ref(false);

// Initialize sidebar state from localStorage, default to false (expanded)
const getSavedSidebarState = () => {
    if (typeof window !== 'undefined' && window.localStorage) {
        const saved = localStorage.getItem('sidebarCollapsed');
        return saved ? JSON.parse(saved) : false;
    }
    return false;
};

const sidebarCollapsed = ref(getSavedSidebarState());

const page = usePage();
const alert = ref(null);

// Watch for sidebar state changes and save to localStorage
watch(sidebarCollapsed, (newValue) => {
    if (typeof window !== 'undefined' && window.localStorage) {
        localStorage.setItem('sidebarCollapsed', JSON.stringify(newValue));
    }
}, { immediate: false });

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
        || route().current('partners.*')
        || route().current('roles.*')
        || route().current('users.*')
});

const isAccountingActive = computed(() => {
    return route().current('accounts.*')
        || route().current('currencies.*')
        || route().current('journals.*')
        || route().current('external-payables.*')
        || route().current('external-receivables.*')
        || route().current('internal-debts.*')
        || route().current('cash-receipt-journals.*')
        || route().current('cash-payment-journals.*')
        || route().current('general-ledger.*')
        || route().current('cash-bank-book.*')
        || route().current('income.*')
        || route().current('balance-sheet.*');
});

const isAssetActive = computed(() => {
    return route().current('asset-categories.*')
        || route().current('assets.*')
        || route().current('asset-purchases.*')
        || route().current('asset-rentals.*')
        || route().current('asset-sales.*')
        || route().current('asset-disposals.*')
        || route().current('asset-invoice-payments.*')
        || route().current('asset-financing-agreements.*')
        || route().current('asset-financing-payments.*')
        || route().current('asset-depreciations.*')
        || route().current('asset-transfers.*');
});

const sidebarWidth = computed(() => {
    return sidebarCollapsed.value ? 'w-16' : 'w-72';
});

const mainContentLeft = computed(() => {
    return sidebarCollapsed.value ? 'md:left-16' : 'md:left-72';
});

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
}
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex flex-col">
        <nav class="bg-white border-b border-black no-print">
            <div class="pl-6">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                            </Link>
                        </div>
                    </div>

                    <div class="hidden md:flex md:items-center md:ms-6">
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

                    <div class="-me-2 flex items-center md:hidden">
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
            <div :class="{'block': showingMobileMenu, 'hidden': !showingMobileMenu}" class="md:hidden bg-white fixed inset-0 top-16 z-50">
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
                            <ResponsiveNavLink :href="route('external-payables.index')" 
                                :active="route().current('external-payables.*') || route().current('external-receivables.*')" 
                                class="pl-11"
                            >
                                Hutang / Piutang
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('internal-debts.index')" 
                                :active="route().current('internal-debts.*')" 
                                class="pl-11"
                            >
                                Hutang Internal
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

                    <!-- Asset Section -->
                    <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isAssetActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <BuildingOffice2Icon class="h-5 w-5 mr-2" />
                            <span>Aset</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink :href="route('asset-categories.index')" :active="route().current('asset-categories.*')" class="pl-11">
                                Kategori Aset
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('assets.index')" :active="route().current('assets.*')" class="pl-11">
                                Daftar Aset
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('asset-purchases.index')" 
                                :active="
                                    route().current('asset-purchases.*')
                                    || route().current('asset-rentals.*')
                                    || route().current('asset-sales.*')
                                    || route().current('asset-invoice-payments.*')
                                " 
                                class="pl-11">
                                Invoice & Pembayaran Aset
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('asset-disposals.index')" :active="route().current('asset-disposals.*')" class="pl-11">
                                Pelepasan Aset
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('asset-depreciations.index')" :active="route().current('asset-depreciations.*')" class="pl-11">
                                Penyusutan/Amortisasi
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('asset-financing-agreements.index')" :active="route().current('asset-financing-agreements.*') || route().current('asset-financing-payments.*')" class="pl-11">
                                Perjanjian Pembiayaan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('asset-transfers.index')" :active="route().current('asset-transfers.*')" class="pl-11">
                                Transfer Aset
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
                            <ResponsiveNavLink :href="route('partners.index')" :active="route().current('partners.*')" class="pl-11">
                                Partner Bisnis
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
            <div :class="[sidebarWidth, sidebarCollapsed ? 'overflow-none' : 'overflow-auto', 'hidden md:shadow-sm md:block bg-white border-t border-r border-gray-200 flex-shrink-0 thin-scrollbar fixed top-16 bottom-0 left-0 no-print transition-all duration-300 z-10']">
                <div :class="sidebarCollapsed ? 'py-2 px-1' : 'py-6 pl-2 pr-6'">
                    <nav class="space-y-1">
                        <!-- Dashboard Link -->
                        <div class="relative group">
                            <NavLink 
                                :href="route('dashboard')" 
                                :active="route().current('dashboard')" 
                                :class="sidebarCollapsed ? 'flex items-center justify-center p-2' : 'flex items-center'"
                                :title="sidebarCollapsed ? 'Dashboard' : ''"
                            >
                                <HomeIcon class="h-6 w-6" :class="sidebarCollapsed ? '' : 'mr-2'" />
                                <span v-if="!sidebarCollapsed">Dashboard</span>
                            </NavLink>
                            
                            <!-- Tooltip for collapsed state -->
                            <div v-if="sidebarCollapsed" class="absolute left-full top-0 ml-2 px-2 py-1 bg-gray-800 text-white text-sm rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50">
                                Dashboard
                            </div>
                        </div>

                        <!-- Accounting Section -->
                        <div class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <BanknotesIcon class="h-6 w-6" />
                                </div>
                                
                                <!-- Side dropdown for collapsed state -->
                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Akuntansi & Keuangan</div>
                                        <NavLink :href="route('accounts.index')" 
                                            :active="route().current('accounts.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Bagan Akun
                                        </NavLink>
                                        <NavLink :href="route('currencies.index')"
                                            :active="route().current('currencies.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Mata Uang
                                        </NavLink>
                                        <NavLink :href="route('journals.index')" 
                                            :active="
                                                route().current('journals.*') 
                                                || route().current('cash-receipt-journals.*') 
                                                || route().current('cash-payment-journals.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Jurnal
                                        </NavLink>
                                        <NavLink :href="route('external-payables.index')" 
                                            :active="route().current('external-payables.*') || route().current('external-receivables.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded">
                                            Hutang / Piutang
                                        </NavLink>
                                        <NavLink :href="route('internal-debts.index')"
                                            :active="route().current('internal-debts.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Hutang Internal
                                        </NavLink>
                                        <NavLink 
                                            :href="route('general-ledger.index')" 
                                            :active="
                                                route().current('general-ledger.*') 
                                                || route().current('cash-bank-book.*') 
                                                || route().current('income.*')
                                                || route().current('balance-sheet.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Laporan Akuntansi
                                        </NavLink>
                                    </div>
                                </div>
                                
                                <!-- Invisible bridge to maintain hover -->
                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>
                            
                            <template v-else>
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
                                        <NavLink :href="route('external-payables.index')" 
                                            :active="route().current('external-payables.*') || route().current('external-receivables.*')" 
                                            class="flex items-center">
                                            Hutang / Piutang
                                        </NavLink>
                                        <NavLink :href="route('internal-debts.index')"
                                            :active="route().current('internal-debts.*')"
                                            class="flex items-center"
                                        >
                                            Hutang Internal
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
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Asset Section -->
                        <div class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <BuildingOffice2Icon class="h-6 w-6" />
                                </div>
                                
                                <!-- Side dropdown for collapsed state -->
                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Aset</div>
                                        <NavLink :href="route('asset-categories.index')"
                                            :active="route().current('asset-categories.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Kategori Aset
                                        </NavLink>
                                        <NavLink :href="route('assets.index')"
                                            :active="route().current('assets.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Daftar Aset
                                        </NavLink>
                                        <NavLink :href="route('asset-purchases.index')"
                                            :active="
                                                route().current('asset-purchases.*')
                                                || route().current('asset-rentals.*')
                                                || route().current('asset-sales.*')
                                                || route().current('asset-invoice-payments.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Invoice & Pembayaran Aset
                                        </NavLink>
                                        <NavLink :href="route('asset-disposals.index')"
                                            :active="
                                                route().current('asset-disposals.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Pelepasan Aset
                                        </NavLink>
                                        <NavLink :href="route('asset-depreciations.index')"
                                            :active="
                                                route().current('asset-depreciations.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Penyusutan/Amortisasi
                                        </NavLink>
                                        <NavLink :href="route('asset-financing-agreements.index')" :active="route().current('asset-financing-agreements.*') || route().current('asset-financing-payments.*')" class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded">
                                            Perjanjian Pembiayaan
                                        </NavLink>
                                        <NavLink :href="route('asset-transfers.index')" :active="route().current('asset-transfers.*')" class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded">
                                            Transfer Aset
                                        </NavLink>
                                    </div>
                                </div>
                                
                                <!-- Invisible bridge to maintain hover -->
                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>
                            
                            <template v-else>
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isAssetActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <BuildingOffice2Icon class="h-6 w-6 mr-2" />
                                        <span>Aset</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink :href="route('asset-categories.index')"
                                            :active="route().current('asset-categories.*')" 
                                            class="flex items-center"
                                        >
                                            Kategori Aset
                                        </NavLink>
                                        <NavLink :href="route('assets.index')"
                                            :active="route().current('assets.*')" 
                                            class="flex items-center"
                                        >
                                            Daftar Aset
                                        </NavLink>
                                        <NavLink :href="route('asset-purchases.index')"
                                            :active="
                                                route().current('asset-purchases.*')
                                                || route().current('asset-rentals.*')
                                                || route().current('asset-sales.*')
                                                || route().current('asset-invoice-payments.*')
                                            " 
                                            class="flex items-center"
                                        >
                                            Invoice & Pembayaran Aset
                                        </NavLink>
                                        <NavLink :href="route('asset-disposals.index')"
                                            :active="
                                                route().current('asset-disposals.*')
                                            " 
                                            class="flex items-center"
                                        >
                                            Pelepasan Aset
                                        </NavLink>
                                        <NavLink :href="route('asset-depreciations.index')"
                                            :active="
                                                route().current('asset-depreciations.*')
                                            " 
                                            class="flex items-center"
                                        >
                                            Penyusutan/Amortisasi
                                        </NavLink>
                                        <NavLink :href="route('asset-financing-agreements.index')" :active="route().current('asset-financing-agreements.*') || route().current('asset-financing-payments.*')" class="flex items-center">
                                            Perjanjian Pembiayaan
                                        </NavLink>
                                        <NavLink :href="route('asset-transfers.index')" :active="route().current('asset-transfers.*')" class="flex items-center">
                                            Transfer Aset
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>
                        
                        <!-- Settings Section -->
                        <div class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <Cog8ToothIcon class="h-6 w-6" />
                                </div>
                                
                                <!-- Side dropdown for collapsed state -->
                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Pengaturan</div>
                                        <NavLink :href="route('dashboard')" :active="route().current('/')" class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded">
                                            General
                                        </NavLink>
                                        <NavLink 
                                            :href="route('companies.index')" 
                                            :active="
                                                route().current('companies.*') 
                                                || route().current('branches.*') 
                                                || route().current('branch-groups.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Perusahaan
                                        </NavLink>
                                        <NavLink :href="route('partners.index')"
                                            :active="route().current('partners.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Partner Bisnis
                                        </NavLink>
                                        <NavLink 
                                            :href="route('roles.index')" 
                                            :active="
                                                route().current('roles.*')
                                                || route().current('users.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Hak Akses Pengguna
                                        </NavLink>
                                    </div>
                                </div>
                                
                                <!-- Invisible bridge to maintain hover -->
                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>
                            
                            <template v-else>
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
                                        <NavLink :href="route('partners.index')"
                                            :active="route().current('partners.*')" 
                                            class="flex items-center"
                                        >
                                            Partner Bisnis
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
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>
                        
                    </nav>
                </div>
            </div>

            <!-- Toggle Button - Outside sidebar -->
            <button 
                @click="toggleSidebar"
                :class="[
                    sidebarCollapsed ? 'left-16' : 'left-72',
                    'hidden md:block fixed top-16 bg-white border-t border-r border-b border-gray-200 rounded-r-md shadow-sm p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-50 transition-all duration-300 z-40 no-print'
                ]"
                :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            >
                <ChevronRightIcon 
                    :class="[
                        sidebarCollapsed ? 'transform rotate-0' : 'transform rotate-180',
                        'h-5 w-5 transition-transform duration-300'
                    ]" 
                />
            </button>
            
            <!-- Main Content -->
            <div :class="[mainContentLeft, 'flex-1 overflow-y-auto thin-scrollbar border-t border-gray-200 fixed top-16 bottom-0 right-0 left-0 main-content print:p-0 print:m-0 print:bg-white print:left-0 print:w-full print:h-full z-0 transition-all duration-300']">
                <!-- Page Heading -->
                <header class="bg-gray-100 no-print">
                    <div class="min-w-max sm:min-w-min md:max-w-full mx-auto pt-8 pb-6 pl-10">
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
                <main class="px-10">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>