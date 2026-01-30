<script setup>
import { ref, nextTick, watch, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { BanknotesIcon, HomeIcon, BuildingOffice2Icon, ArchiveBoxIcon, CubeIcon, ShoppingCartIcon, CurrencyDollarIcon, PuzzlePieceIcon, CalendarDaysIcon, CalculatorIcon } from '@heroicons/vue/24/solid';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue';
import { Cog8ToothIcon, ChevronRightIcon, Bars3Icon } from '@heroicons/vue/24/solid';
import { QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';
import AlertNotification from '@/Components/AlertNotification.vue';
import OnboardingTour from '@/Components/OnboardingTour.vue';
import HelpDocumentation from '@/Components/HelpDocumentation.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { hasModuleAccess, isModuleEnabled, can } = usePermissions();

const showingNavigationDropdown = ref(false);
const showingMobileMenu = ref(false);
const showHelp = ref(false);

// Module-level permission checks (for section visibility)
// These combine role permissions AND company module access
const canAccessSettings = computed(() => hasModuleAccess('settings'));
const canAccessPurchase = computed(() => hasModuleAccess('purchase'));
const canAccessSales = computed(() => hasModuleAccess('sales'));
const canAccessBooking = computed(() => isModuleEnabled('booking'));
const canAccessInventory = computed(() => hasModuleAccess('inventory'));
const canAccessAccounting = computed(() => hasModuleAccess('accounting'));
const canAccessManufacturing = computed(() => hasModuleAccess('manufacturing'));
const canAccessCatalog = computed(() => hasModuleAccess('catalog'));
const canAccessAssets = computed(() => isModuleEnabled('assets'));
const canAccessCosting = computed(() => isModuleEnabled('costing'));

// Resource-level permission checks (for sub-menu visibility)
// Settings
const canViewCompanies = computed(() => can('settings.companies.view'));
const canViewBranches = computed(() => can('settings.branches.view'));
const canViewRoles = computed(() => can('settings.roles.view'));
const canViewUsers = computed(() => can('settings.users.view'));
const canViewPartners = computed(() => can('settings.partners.view'));

// Purchase
const canViewPurchasePlans = computed(() => can('purchase.purchase_plan.view'));
const canViewPurchaseOrders = computed(() => can('purchase.purchase_order.view'));
const canViewGoodsReceipts = computed(() => can('purchase.goods_receipt.view'));
const canViewPurchaseInvoices = computed(() => can('purchase.purchase_invoice.view'));
const canViewPurchaseReturns = computed(() => can('purchase.purchase_return.view'));

// Sales
const canViewSalesOrders = computed(() => can('sales.sales_order.view'));
const canViewDeliveries = computed(() => can('sales.delivery_order.view'));
const canViewSalesInvoices = computed(() => can('sales.sales_invoice.view'));
const canViewSalesReturns = computed(() => can('sales.sales_return.view'));

// Inventory
const canViewStock = computed(() => can('inventory.stock.view'));
const canViewTransfers = computed(() => can('inventory.transfer.view'));
const canViewAdjustments = computed(() => can('inventory.adjustment.view'));

// Accounting
const canViewJournals = computed(() => can('accounting.journal.view'));
const canViewPayments = computed(() => can('accounting.payment.view'));
const canViewReceipts = computed(() => can('accounting.receipt.view'));

// Manufacturing
const canViewBom = computed(() => can('manufacturing.bom.view'));
const canViewWorkOrders = computed(() => can('manufacturing.work_order.view'));
const canViewProduction = computed(() => can('manufacturing.production.view'));

// Catalog
const canViewProducts = computed(() => can('catalog.products.view'));
const canViewAccounts = computed(() => can('catalog.accounts.view'));
const canViewCurrencies = computed(() => can('catalog.currencies.view'));

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
        || route().current('shipping-providers.*')
        || route().current('roles.*')
        || route().current('users.*')
        || route().current('tax-jurisdictions.*')
        || route().current('tax-components.*')
        || route().current('tax-categories.*')
        || route().current('company-bank-accounts.*')
        || route().current('tax-rules.*')
        || route().current('gl-event-configurations.*')
        || route().current('document-templates.*');
});

const isAccountingActive = computed(() => {
    return route().current('accounts.*')
        || route().current('currencies.*')
        || route().current('journals.*')
        || route().current('external-payables.*')
        || route().current('external-receivables.*')
        || route().current('external-payable-payments.*')
        || route().current('external-receivable-payments.*')
        || route().current('internal-debts.*')
        || route().current('internal-debt-payments.*')
        || route().current('internal-debt-aging.*')
        || route().current('cash-receipt-journals.*')
        || route().current('cash-payment-journals.*')
        || route().current('general-ledger.*')
        || route().current('cash-bank-book.*')
        || route().current('income.*')
        || route().current('balance-sheet.*')
        || route().current('external-payable-aging.*')
        || route().current('external-payable-mutation.*')
        || route().current('external-payable-card.*')
        || route().current('external-receivable-aging.*')
        || route().current('external-receivable-mutation.*')
        || route().current('external-receivable-card.*')
        || route().current('operational-reconciliation.*');
});

const isCostingActive = computed(() => {
    return route().current('costing.cost-entries.*')
        || route().current('costing.cost-pools.*')
        || route().current('costing.cost-items.*')
        || route().current('costing.cost-allocations.*');
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
        || route().current('asset-transfers.*')
        || route().current('asset-maintenances.*');
});

const isProductsActive = computed(() => {   
    return route().current('catalog.product-categories.*')
        || route().current('catalog.products.*')
        || route().current('catalog.user-discount-limits.*')
        || route().current('catalog.price-lists.*')
        || route().current('catalog.price-list-targets.*')
        || route().current('catalog.price-list-items.*');
});

const isInventoryActive = computed(() => {
    return route().current('inventory.receipts.*')
        || route().current('inventory.shipments.*')
        || route().current('inventory.adjustments.*')
        || route().current('inventory.transfers.*');
});

const isPurchasingActive = computed(() => {
    return route().current('purchase-plans.*')
        || route().current('purchase-orders.*')
        || route().current('goods-receipts.*')
        || route().current('purchase-invoices.*')
        || route().current('purchase-returns.*')
        || route().current('purchasing-reports.*');
});

const isSalesActive = computed(() => {
    return route().current('sales-orders.*')
        || route().current('sales-deliveries.*')
        || route().current('sales-invoices.*')
        || route().current('sales-returns.*')
        || route().current('sales-reports.*');
});

const isBookingActive = computed(() => {
    return route().current('bookings.*')
        || route().current('resource-pools.*')
        || route().current('resource-instances.*');
});

const isProduksiActive = computed(() => {
    return route().current('bill-of-materials.*')
        || route().current('work-orders.*')
        || route().current('component-issues.*')
        || route().current('component-scraps.*')
        || route().current('finished-goods-receipts.*');
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

// Onboarding
const showOnboarding = computed(() => {
    const onboarding = page.props.onboarding;
    if (!onboarding) return false;
    return !onboarding.completed && !onboarding.skipped;
});

const onboardingStep = computed(() => {
    return page.props.onboarding?.currentStep || 0;
});
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
                                    <DropdownLink :href="route('tenant.logout')" method="post" as="button">
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

                    <!-- Purchasing Section -->
                    <Disclosure v-if="canAccessPurchase" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isPurchasingActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <ShoppingCartIcon class="h-5 w-5 mr-2" />
                            <span>Pembelian</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink
                                v-if="canViewPurchasePlans"
                                :href="route('purchase-plans.index')"
                                :active="route().current('purchase-plans.*')"
                                class="pl-11"
                            >
                                Rencana Pembelian
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewPurchaseOrders"
                                :href="route('purchase-orders.index')"
                                :active="route().current('purchase-orders.*')"
                                class="pl-11"
                            >
                                Purchase Orders
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewGoodsReceipts"
                                :href="route('goods-receipts.index')"
                                :active="route().current('goods-receipts.*')"
                                class="pl-11"
                            >
                                Penerimaan Pembelian
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewPurchaseInvoices"
                                :href="route('purchase-invoices.index')"
                                :active="route().current('purchase-invoices.*')"
                                class="pl-11"
                            >
                                Faktur Pembelian
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewPurchaseReturns"
                                :href="route('purchase-returns.index')"
                                :active="route().current('purchase-returns.*')"
                                class="pl-11"
                            >
                                Retur Pembelian
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewPurchaseOrders"
                                :href="route('purchasing-reports.index')"
                                :active="route().current('purchasing-reports.*')"
                                class="pl-11"
                            >
                                Laporan Pembelian
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Sales Section -->
                    <Disclosure v-if="canAccessSales" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isSalesActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <CurrencyDollarIcon class="h-5 w-5 mr-2" />
                            <span>Penjualan</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink
                                v-if="canViewSalesOrders"
                                :href="route('sales-orders.index')"
                                :active="route().current('sales-orders.*')"
                                class="pl-11"
                            >
                                Sales Order
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewDeliveries"
                                :href="route('sales-deliveries.index')"
                                :active="route().current('sales-deliveries.*')"
                                class="pl-11"
                            >
                                Pengiriman Penjualan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewSalesInvoices"
                                :href="route('sales-invoices.index')"
                                :active="route().current('sales-invoices.*')"
                                class="pl-11"
                            >
                                Faktur Penjualan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewSalesReturns"
                                :href="route('sales-returns.index')"
                                :active="route().current('sales-returns.*')"
                                class="pl-11"
                            >
                                Retur Penjualan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewSalesOrders"
                                :href="route('sales-reports.index')"
                                :active="route().current('sales-reports.*')"
                                class="pl-11"
                            >
                                Laporan Penjualan
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Booking Section -->
                    <Disclosure v-if="canAccessBooking" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isBookingActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <CalendarDaysIcon class="h-5 w-5 mr-2" />
                            <span>Booking</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink
                                :href="route('bookings.index')"
                                :active="route().current('bookings.*')"
                                class="pl-11"
                            >
                                Daftar Booking
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('resource-pools.index')"
                                :active="route().current('resource-pools.*')"
                                class="pl-11"
                            >
                                Resource Pool
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('resource-instances.index')"
                                :active="route().current('resource-instances.*')"
                                class="pl-11"
                            >
                                Resource Instance
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Produksi Section -->
                    <Disclosure v-if="canAccessManufacturing" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isProduksiActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <PuzzlePieceIcon class="h-5 w-5 mr-2" />
                            <span>Produksi</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink
                                :href="route('bill-of-materials.index')"
                                :active="route().current('bill-of-materials.*')"
                                class="pl-11"
                            >
                                Bill of Materials
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('work-orders.index')"
                                :active="route().current('work-orders.*')"
                                class="pl-11"
                            >
                                Surat Perintah Produksi
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('component-issues.index')"
                                :active="route().current('component-issues.*')"
                                class="pl-11"
                            >
                                Pengeluaran Bahan Baku
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('finished-goods-receipts.index')"
                                :active="route().current('finished-goods-receipts.*')"
                                class="pl-11"
                            >
                                Penerimaan Produk Jadi
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('component-scraps.index')"
                                :active="route().current('component-scraps.*')"
                                class="pl-11"
                            >
                                Pembuangan Bahan Baku
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Products Section -->
                    <Disclosure v-if="canAccessCatalog" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isProductsActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <ArchiveBoxIcon class="h-5 w-5 mr-2" />
                            <span>Produk</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink :href="route('catalog.product-categories.index')" :active="route().current('catalog.product-categories.*')" class="pl-11">
                                Kategori Produk
                            </ResponsiveNavLink>
                            <ResponsiveNavLink 
                                :href="route('catalog.products.index', 'trade')" 
                                :active="route().current('catalog.products.*')" 
                                class="pl-11">
                                Katalog Produk
                            </ResponsiveNavLink>
                            <ResponsiveNavLink 
                                :href="route('catalog.user-discount-limits.index')" 
                                :active="route().current('catalog.user-discount-limits.*')" 
                                class="pl-11">
                                Batas Diskon
                            </ResponsiveNavLink>
                            <ResponsiveNavLink 
                                :href="route('catalog.price-lists.index')" 
                                :active="
                                    route().current('catalog.price-lists.*')
                                    || route().current('catalog.price-list-targets.*')
                                    || route().current('catalog.price-list-items.*')
                                " 
                                class="pl-11">
                                Daftar Harga
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Inventory Section -->
                    <Disclosure v-if="canAccessInventory" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isInventoryActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <CubeIcon class="h-5 w-5 mr-2" />
                            <span>Persediaan</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink
                                :href="route('inventory.receipts.index')"
                                :active="route().current('inventory.receipts.*')"
                                class="pl-11"
                            >
                                Penerimaan Barang
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('inventory.shipments.index')"
                                :active="route().current('inventory.shipments.*')"
                                class="pl-11"
                            >
                                Pengeluaran Barang
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('inventory.adjustments.index')"
                                :active="route().current('inventory.adjustments.*')"
                                class="pl-11"
                            >
                                Penyesuaian Stok
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('inventory.transfers.index')"
                                :active="route().current('inventory.transfers.*')"
                                class="pl-11"
                            >
                                Transfer Antar Lokasi
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Accounting Section -->
                    <Disclosure v-if="canAccessAccounting" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isAccountingActive">
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
                            <ResponsiveNavLink :href="route('sales-invoices.index')"
                                :active="route().current('sales-invoices.*')"
                                class="pl-11"
                            >
                                Faktur Penjualan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('external-payables.index')" 
                                :active="route().current('external-payables.*') 
                                    || route().current('external-receivables.*')
                                    || route().current('external-payable-payments.*')
                                    || route().current('external-receivable-payments.*')" 
                                class="pl-11"
                            >
                                Hutang / Piutang
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('internal-debts.index')" 
                                :active="
                                    route().current('internal-debts.*')
                                    || route().current('internal-debt-payments.*')
                                    || route().current('internal-debt-aging.*')
                                "
                                class="pl-11"
                            >
                                Hutang / Piutang Internal
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('operational-reconciliation.index')" 
                                :active="
                                    route().current('general-ledger.*') 
                                    || route().current('cash-bank-book.*') 
                                    || route().current('income.*') 
                                    || route().current('balance-sheet.*')
                                    || route().current('external-payable-aging.*')
                                    || route().current('external-payable-mutation.*')
                                    || route().current('external-payable-card.*')
                                    || route().current('external-receivable-aging.*')
                                    || route().current('external-receivable-mutation.*')
                                    || route().current('external-receivable-card.*')
                                    || route().current('operational-reconciliation.*')
                                " 
                                class="pl-11"
                            >
                                Laporan Akuntansi
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Costing Section -->
                    <Disclosure v-if="canAccessCosting" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isCostingActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <CalculatorIcon class="h-5 w-5 mr-2" />
                            <span>Manajemen Biaya</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink
                                :href="route('costing.cost-entries.index')"
                                :active="route().current('costing.cost-entries.*')"
                                class="pl-11"
                            >
                                Catatan Biaya
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('costing.cost-pools.index')"
                                :active="route().current('costing.cost-pools.*')"
                                class="pl-11"
                            >
                                Pool Biaya
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('costing.cost-items.index')"
                                :active="route().current('costing.cost-items.*')"
                                class="pl-11"
                            >
                                Cost Items
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('costing.cost-allocations.index')"
                                :active="route().current('costing.cost-allocations.*')"
                                class="pl-11"
                            >
                                Alokasi Biaya
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Asset Section -->
                    <Disclosure v-if="canAccessAssets" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isAssetActive">
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
                            <ResponsiveNavLink :href="route('asset-maintenances.index')" :active="route().current('asset-maintenances.*')" class="pl-11">
                                Pemeliharaan Aset
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Settings Section -->
                    <Disclosure v-if="canAccessSettings" v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isSettingsActive">
                        <DisclosureButton class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50">
                            <Cog8ToothIcon class="h-5 w-5 mr-2" />
                            <span>Pengaturan</span>
                            <ChevronRightIcon
                                :class="open ? 'transform rotate-90' : ''"
                                class="ml-auto h-4 w-4 text-gray-400"
                            />
                        </DisclosureButton>
                        <DisclosurePanel class="mt-1 space-y-1 text-sm">
                            <ResponsiveNavLink
                                v-if="canViewCompanies"
                                :href="route('general-settings.index')"
                                :active="route().current('general-settings.*')"
                                class="pl-11"
                            >
                                General
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewCompanies"
                                :href="route('companies.index')"
                                :active="route().current('companies.*') || route().current('branches.*') || route().current('branch-groups.*')"
                                class="pl-11"
                            >
                                Perusahaan
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canViewPartners" :href="route('partners.index')" :active="route().current('partners.*')" class="pl-11">
                                Partner Bisnis
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewCompanies"
                                :href="route('shipping-providers.index')"
                                :active="route().current('shipping-providers.*')"
                                class="pl-11"
                            >
                                Penyedia Pengiriman
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewRoles"
                                :href="route('roles.index')"
                                :active="
                                    route().current('roles.*')
                                    || route().current('users.*')
                                "
                                class="pl-11"
                            >
                                Hak Akses Pengguna
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canViewCompanies" :href="route('company-bank-accounts.index')" :active="route().current('company-bank-accounts.*')" class="pl-11">
                                Rekening Bank
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                v-if="canViewCompanies"
                                :href="route('gl-event-configurations.index')"
                                :active="route().current('gl-event-configurations.*')"
                                class="pl-11"
                            >
                                Konfigurasi GL Event
                            </ResponsiveNavLink>
                            <ResponsiveNavLink 
                                v-if="canViewCompanies"
                                :href="route('tax-jurisdictions.index')" 
                                :active="
                                    route().current('tax-jurisdictions.*')
                                    || route().current('tax-components.*')
                                    || route().current('tax-categories.*')
                                    || route().current('tax-rules.*')
                                " 
                                class="pl-11"
                            >
                                Pajak
                            </ResponsiveNavLink>
                            <ResponsiveNavLink 
                                v-if="canViewCompanies"
                                :href="route('document-templates.index')" 
                                :active="route().current('document-templates.*')" 
                                class="pl-11"
                            >
                                Template Dokumen
                            </ResponsiveNavLink>
                        </DisclosurePanel>
                    </Disclosure>

                    <!-- Help Link -->
                    <button 
                        @click="showHelp = true; showingMobileMenu = false;"
                        class="flex items-center w-full text-left px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 mt-2"
                    >
                        <QuestionMarkCircleIcon class="h-5 w-5 mr-2" />
                        <span>Bantuan</span>
                    </button>
                </div>

                <!-- Existing profile section -->
                <div class="pt-4 pb-1 border-t border-gray-200">
                    <div class="px-4">
                        <div class="font-medium text-base text-gray-800">{{ $page.props.auth.user.name }}</div>
                        <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('tenant.logout')" method="post" as="button">
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

                        <!-- Purchasing Section -->
                        <div v-if="canAccessPurchase" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <ShoppingCartIcon class="h-6 w-6" />
                                </div>

                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Purchase Flow</div>
                                        <NavLink
                                            :href="route('purchase-plans.index')"
                                            :active="route().current('purchase-plans.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Rencana Pembelian
                                        </NavLink>
                                        <NavLink
                                            :href="route('purchase-orders.index')"
                                            :active="route().current('purchase-orders.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Purchase Orders
                                        </NavLink>
                                    <NavLink
                                        :href="route('goods-receipts.index')"
                                        :active="route().current('goods-receipts.*')"
                                        class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                    >
                                        Penerimaan Pembelian
                                    </NavLink>
                                        <NavLink
                                            :href="route('purchase-invoices.index')"
                                            :active="route().current('purchase-invoices.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Faktur Pembelian
                                        </NavLink>
                                        <NavLink
                                            :href="route('purchase-returns.index')"
                                            :active="route().current('purchase-returns.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Retur Pembelian
                                        </NavLink>
                                        <NavLink
                                            :href="route('purchasing-reports.index')"
                                            :active="route().current('purchasing-reports.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Laporan Pembelian
                                        </NavLink>
                                    </div>
                                </div>

                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>

                            <template v-else>
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isPurchasingActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <ShoppingCartIcon class="h-6 w-6 mr-2" />
                                        <span>Pembelian</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink
                                            v-if="canViewPurchasePlans"
                                            :href="route('purchase-plans.index')"
                                            :active="route().current('purchase-plans.*')"
                                            class="flex items-center"
                                        >
                                            Rencana Pembelian
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewPurchaseOrders"
                                            :href="route('purchase-orders.index')"
                                            :active="route().current('purchase-orders.*')"
                                            class="flex items-center"
                                        >
                                            Purchase Orders
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewGoodsReceipts"
                                            :href="route('goods-receipts.index')"
                                            :active="route().current('goods-receipts.*')"
                                            class="flex items-center"
                                        >
                                            Penerimaan Pembelian
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewPurchaseInvoices"
                                            :href="route('purchase-invoices.index')"
                                            :active="route().current('purchase-invoices.*')"
                                            class="flex items-center"
                                        >
                                            Faktur Pembelian
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewPurchaseReturns"
                                            :href="route('purchase-returns.index')"
                                            :active="route().current('purchase-returns.*')"
                                            class="flex items-center"
                                        >
                                            Retur Pembelian
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewPurchaseOrders"
                                            :href="route('purchasing-reports.index')"
                                            :active="route().current('purchasing-reports.*')"
                                            class="flex items-center"
                                        >
                                            Laporan Pembelian
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Sales Section -->
                        <div v-if="canAccessSales" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <CurrencyDollarIcon class="h-6 w-6" />
                                </div>

                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Penjualan</div>
                                        <NavLink
                                            v-if="canViewSalesOrders"
                                            :href="route('sales-orders.index')"
                                            :active="route().current('sales-orders.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Sales Order
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewDeliveries"
                                            :href="route('sales-deliveries.index')"
                                            :active="route().current('sales-deliveries.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Pengiriman Penjualan
                                        </NavLink>
                                        <NavLink v-if="canViewSalesInvoices" :href="route('sales-invoices.index')"
                                            :active="route().current('sales-invoices.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Faktur Penjualan
                                        </NavLink>
                                        <NavLink v-if="canViewSalesReturns" :href="route('sales-returns.index')"
                                            :active="route().current('sales-returns.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Retur Penjualan
                                        </NavLink>
                                        <NavLink v-if="canViewSalesOrders" :href="route('sales-reports.index')"
                                            :active="route().current('sales-reports.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Laporan Penjualan
                                        </NavLink>
                                    </div>
                                </div>

                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>

                            <template v-else>
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isSalesActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <CurrencyDollarIcon class="h-6 w-6 mr-2" />
                                        <span>Penjualan</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink
                                            v-if="canViewSalesOrders"
                                            :href="route('sales-orders.index')"
                                            :active="route().current('sales-orders.*')"
                                            class="flex items-center"
                                        >
                                            Sales Order
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewDeliveries"
                                            :href="route('sales-deliveries.index')"
                                            :active="route().current('sales-deliveries.*')"
                                            class="flex items-center"
                                        >
                                            Pengiriman Penjualan
                                        </NavLink>
                                        <NavLink v-if="canViewSalesInvoices" :href="route('sales-invoices.index')"
                                            :active="route().current('sales-invoices.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Faktur Penjualan
                                        </NavLink>
                                        <NavLink v-if="canViewSalesReturns" :href="route('sales-returns.index')"
                                            :active="route().current('sales-returns.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Retur Penjualan
                                        </NavLink>
                                        <NavLink v-if="canViewSalesOrders" :href="route('sales-reports.index')"
                                            :active="route().current('sales-reports.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Laporan Penjualan
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Booking Section -->
                        <div v-if="canAccessSales" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <CalendarDaysIcon class="h-6 w-6" />
                                </div>

                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Booking</div>
                                        <NavLink
                                            :href="route('bookings.index')"
                                            :active="route().current('bookings.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Daftar Booking
                                        </NavLink>
                                        <NavLink
                                            :href="route('resource-pools.index')"
                                            :active="route().current('resource-pools.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Resource Pool
                                        </NavLink>
                                        <NavLink
                                            :href="route('resource-instances.index')"
                                            :active="route().current('resource-instances.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Resource Instance
                                        </NavLink>
                                    </div>
                                </div>

                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>

                            <template v-else-if="canAccessBooking">
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isBookingActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <CalendarDaysIcon class="h-6 w-6 mr-2" />
                                        <span>Booking</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink
                                            :href="route('bookings.index')"
                                            :active="route().current('bookings.*')"
                                            class="flex items-center"
                                        >
                                            Daftar Booking
                                        </NavLink>
                                        <NavLink
                                            :href="route('resource-pools.index')"
                                            :active="route().current('resource-pools.*')"
                                            class="flex items-center"
                                        >
                                            Resource Pool
                                        </NavLink>
                                        <NavLink
                                            :href="route('resource-instances.index')"
                                            :active="route().current('resource-instances.*')"
                                            class="flex items-center"
                                        >
                                            Resource Instance
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Produksi Section -->
                        <div v-if="canAccessManufacturing" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <PuzzlePieceIcon class="h-6 w-6" />
                                </div>

                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Produksi</div>
                                        <NavLink
                                            v-if="canViewBom"
                                            :href="route('bill-of-materials.index')"
                                            :active="route().current('bill-of-materials.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Bill of Materials
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewWorkOrders"
                                            :href="route('work-orders.index')"
                                            :active="route().current('work-orders.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Surat Perintah Produksi
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewProduction"
                                            :href="route('component-issues.index')"
                                            :active="route().current('component-issues.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Pengeluaran Bahan Baku
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewProduction"
                                            :href="route('finished-goods-receipts.index')"
                                            :active="route().current('finished-goods-receipts.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Penerimaan Produk Jadi
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewProduction"
                                            :href="route('component-scraps.index')"
                                            :active="route().current('component-scraps.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Pembuangan Bahan Baku
                                        </NavLink>
                                    </div>
                                </div>

                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>

                            <template v-else>
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isProduksiActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <PuzzlePieceIcon class="h-6 w-6 mr-2" />
                                        <span>Produksi</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink
                                            v-if="canViewBom"
                                            :href="route('bill-of-materials.index')"
                                            :active="route().current('bill-of-materials.*')"
                                            class="flex items-center"
                                        >
                                            Bill of Materials
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewWorkOrders"
                                            :href="route('work-orders.index')"
                                            :active="route().current('work-orders.*')"
                                            class="flex items-center"
                                        >
                                            Surat Perintah Produksi
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewProduction"
                                            :href="route('component-issues.index')"
                                            :active="route().current('component-issues.*')"
                                            class="flex items-center"
                                        >
                                            Pengeluaran Bahan Baku
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewProduction"
                                            :href="route('finished-goods-receipts.index')"
                                            :active="route().current('finished-goods-receipts.*')"
                                            class="flex items-center"
                                        >
                                            Penerimaan Produk Jadi
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewProduction"
                                            :href="route('component-scraps.index')"
                                            :active="route().current('component-scraps.*')"
                                            class="flex items-center"
                                        >
                                            Pembuangan Bahan Baku
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Products Section -->
                        <div v-if="canAccessCatalog" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <ArchiveBoxIcon class="h-6 w-6" />
                                </div>
                                
                                <!-- Side dropdown for collapsed state -->
                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Produk</div>
                                        <NavLink 
                                            v-if="canViewProducts"
                                            :href="route('catalog.product-categories.index')" 
                                            :active="route().current('catalog.product-categories.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Kategori Produk
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewProducts"
                                            :href="route('catalog.products.index')" 
                                            :active="route().current('catalog.products.*', 'trade')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Katalog Produk
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewProducts"
                                            :href="route('catalog.user-discount-limits.index')" 
                                            :active="route().current('catalog.user-discount-limits.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Batas Diskon
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewProducts"
                                            :href="route('catalog.price-lists.index')" 
                                            :active="
                                                route().current('catalog.price-lists.*')
                                                || route().current('catalog.price-list-targets.*')
                                                || route().current('catalog.price-list-items.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Daftar Harga
                                        </NavLink>
                                    </div>
                                </div>
                                
                                <!-- Invisible bridge to maintain hover -->
                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>
                            
                            <template v-else>
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isProductsActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <ArchiveBoxIcon class="h-6 w-6 mr-2" />
                                        <span>Produk</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink v-if="canViewProducts" :href="route('catalog.product-categories.index')" :active="route().current('catalog.product-categories.*')" class="flex items-center">
                                            Kategori Produk
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewProducts"
                                            :href="route('catalog.products.index', 'trade')" 
                                            :active="route().current('catalog.products.*')
                                            " 
                                            class="flex items-center">
                                            Katalog Produk
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewProducts"
                                            :href="route('catalog.user-discount-limits.index')" 
                                            :active="route().current('catalog.user-discount-limits.*')" 
                                            class="flex items-center"
                                        >
                                            Batas Diskon
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewProducts"
                                            :href="route('catalog.price-lists.index')" 
                                            :active="
                                                route().current('catalog.price-lists.*')
                                                || route().current('catalog.price-list-targets.*')
                                                || route().current('catalog.price-list-items.*')
                                            " 
                                            class="flex items-center"
                                        >
                                            Daftar Harga
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Inventory Section -->
                        <div v-if="canAccessInventory" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <CubeIcon class="h-6 w-6" />
                                </div>

                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Persediaan</div>
                                        <NavLink
                                            v-if="canViewStock"
                                            :href="route('inventory.receipts.index')"
                                            :active="route().current('inventory.receipts.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Penerimaan Barang
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewStock"
                                            :href="route('inventory.shipments.index')"
                                            :active="route().current('inventory.shipments.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Pengeluaran Barang
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewAdjustments"
                                            :href="route('inventory.adjustments.index')"
                                            :active="route().current('inventory.adjustments.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Penyesuaian Stok
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewTransfers"
                                            :href="route('inventory.transfers.index')"
                                            :active="route().current('inventory.transfers.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Transfer Antar Lokasi
                                        </NavLink>
                                    </div>
                                </div>

                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>

                            <template v-else>
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isInventoryActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <CubeIcon class="h-6 w-6 mr-2" />
                                        <span>Persediaan</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink
                                            v-if="canViewStock"
                                            :href="route('inventory.receipts.index')"
                                            :active="route().current('inventory.receipts.*')"
                                            class="flex items-center"
                                        >
                                            Penerimaan Barang
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewStock"
                                            :href="route('inventory.shipments.index')"
                                            :active="route().current('inventory.shipments.*')"
                                            class="flex items-center"
                                        >
                                            Pengeluaran Barang
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewAdjustments"
                                            :href="route('inventory.adjustments.index')"
                                            :active="route().current('inventory.adjustments.*')"
                                            class="flex items-center"
                                        >
                                            Penyesuaian Stok
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewTransfers"
                                            :href="route('inventory.transfers.index')"
                                            :active="route().current('inventory.transfers.*')"
                                            class="flex items-center"
                                        >
                                            Transfer Antar Lokasi
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Accounting Section -->
                        <div v-if="canAccessAccounting" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <BanknotesIcon class="h-6 w-6" />
                                </div>
                                
                                <!-- Side dropdown for collapsed state -->
                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Akuntansi & Keuangan</div>
                                        <NavLink v-if="canViewAccounts" :href="route('accounts.index')" 
                                            :active="route().current('accounts.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Bagan Akun
                                        </NavLink>
                                        <NavLink v-if="canViewCurrencies" :href="route('currencies.index')"
                                            :active="route().current('currencies.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Mata Uang
                                        </NavLink>
                                        <NavLink v-if="canViewJournals" :href="route('journals.index')"
                                            :active="
                                                route().current('journals.*')
                                                || route().current('cash-receipt-journals.*')
                                                || route().current('cash-payment-journals.*')
                                            "
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Jurnal
                                        </NavLink>
                                        <NavLink v-if="canViewPayments" :href="route('external-payables.index')" 
                                            :active="route().current('external-payables.*') 
                                                || route().current('external-receivables.*')
                                                || route().current('external-payable-payments.*')
                                                || route().current('external-receivable-payments.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded">
                                            Hutang / Piutang
                                        </NavLink>
                                        <NavLink v-if="canViewPayments" :href="route('internal-debts.index')"
                                            :active="
                                                route().current('internal-debts.*')
                                                || route().current('internal-debt-payments.*')
                                                || route().current('internal-debt-aging.*')
                                            "
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Hutang / Piutang Internal
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewJournals"
                                            :href="route('operational-reconciliation.index')" 
                                            :active="
                                                route().current('operational-reconciliation.*') 
                                                || route().current('general-ledger.*') 
                                                || route().current('cash-bank-book.*') 
                                                || route().current('income.*')
                                                || route().current('balance-sheet.*')
                                                || route().current('external-payable-aging.*')
                                                || route().current('external-payable-mutation.*')
                                                || route().current('external-payable-card.*')
                                                || route().current('external-receivable-aging.*')
                                                || route().current('external-receivable-mutation.*')
                                                || route().current('external-receivable-card.*')
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
                                        <NavLink v-if="canViewAccounts" :href="route('accounts.index')" 
                                            :active="route().current('accounts.*')" 
                                            class="flex items-center"
                                        >
                                            Bagan Akun
                                        </NavLink>
                                        <NavLink v-if="canViewCurrencies" :href="route('currencies.index')"
                                            :active="route().current('currencies.*')" 
                                            class="flex items-center"
                                        >
                                            Mata Uang
                                        </NavLink>
                                        <NavLink v-if="canViewJournals" :href="route('journals.index')"
                                            :active="
                                                route().current('journals.*')
                                                || route().current('cash-receipt-journals.*')
                                                || route().current('cash-payment-journals.*')
                                            "
                                            class="flex items-center"
                                        >
                                            Jurnal
                                        </NavLink>
                                        <NavLink v-if="canViewPayments" :href="route('external-payables.index')" 
                                            :active="route().current('external-payables.*') 
                                                || route().current('external-receivables.*')
                                                || route().current('external-payable-payments.*')
                                                || route().current('external-receivable-payments.*')" 
                                            class="flex items-center">
                                            Hutang / Piutang
                                        </NavLink>
                                        <NavLink v-if="canViewPayments" :href="route('internal-debts.index')"
                                            :active="
                                                route().current('internal-debts.*')
                                                || route().current('internal-debt-payments.*')
                                                || route().current('internal-debt-aging.*')
                                            "
                                            class="flex items-center"
                                        >
                                            Hutang / Piutang Internal
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewJournals"
                                            :href="route('operational-reconciliation.index')" 
                                            :active="
                                                route().current('operational-reconciliation.*') 
                                                || route().current('general-ledger.*') 
                                                || route().current('cash-bank-book.*') 
                                                || route().current('income.*')
                                                || route().current('balance-sheet.*')
                                                || route().current('external-payable-aging.*')
                                                || route().current('external-payable-mutation.*')
                                                || route().current('external-payable-card.*')
                                                || route().current('external-receivable-aging.*')
                                                || route().current('external-receivable-mutation.*')
                                                || route().current('external-receivable-card.*')
                                            " 
                                            class="flex items-center"
                                        >
                                            Laporan Akuntansi
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Costing Section -->
                        <div class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <CalculatorIcon class="h-6 w-6" />
                                </div>

                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Manajemen Biaya</div>
                                        <NavLink
                                            :href="route('costing.cost-entries.index')"
                                            :active="route().current('costing.cost-entries.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Catatan Biaya
                                        </NavLink>
                                        <NavLink
                                            :href="route('costing.cost-pools.index')"
                                            :active="route().current('costing.cost-pools.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Pool Biaya
                                        </NavLink>
                                        <NavLink
                                            :href="route('costing.cost-items.index')"
                                            :active="route().current('costing.cost-items.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Cost Items
                                        </NavLink>
                                        <NavLink
                                            :href="route('costing.cost-allocations.index')"
                                            :active="route().current('costing.cost-allocations.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Alokasi Biaya
                                        </NavLink>
                                    </div>
                                </div>

                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>

                            <template v-else-if="canAccessCosting">
                                <Disclosure v-slot="{ open }" as="div" class="mt-2" :defaultOpen="isCostingActive">
                                    <DisclosureButton class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 focus:outline-none">
                                        <CalculatorIcon class="h-6 w-6 mr-2" />
                                        <span>Manajemen Biaya</span>
                                        <ChevronRightIcon
                                            :class="open ? 'transform rotate-90' : ''"
                                            class="ml-auto h-4 w-4 text-gray-400"
                                        />
                                    </DisclosureButton>
                                    <DisclosurePanel class="mt-2 space-y-2 pl-8">
                                        <NavLink
                                            :href="route('costing.cost-entries.index')"
                                            :active="route().current('costing.cost-entries.*')"
                                            class="flex items-center"
                                        >
                                            Catatan Biaya
                                        </NavLink>
                                        <NavLink
                                            :href="route('costing.cost-pools.index')"
                                            :active="route().current('costing.cost-pools.*')"
                                            class="flex items-center"
                                        >
                                            Pool Biaya
                                        </NavLink>
                                        <NavLink
                                            :href="route('costing.cost-items.index')"
                                            :active="route().current('costing.cost-items.*')"
                                            class="flex items-center"
                                        >
                                            Cost Items
                                        </NavLink>
                                        <NavLink
                                            :href="route('costing.cost-allocations.index')"
                                            :active="route().current('costing.cost-allocations.*')"
                                            class="flex items-center"
                                        >
                                            Alokasi Biaya
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
                                        <NavLink :href="route('asset-maintenances.index')" :active="route().current('asset-maintenances.*')" class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded">
                                            Pemeliharaan Aset
                                        </NavLink>
                                    </div>
                                </div>
                                
                                <!-- Invisible bridge to maintain hover -->
                                <div class="absolute left-full top-0 w-2 h-full opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto z-10"></div>
                            </template>
                            
                            <template v-else-if="canAccessAssets">
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
                                        <NavLink :href="route('asset-maintenances.index')" :active="route().current('asset-maintenances.*')" class="flex items-center">
                                            Pemeliharaan Aset
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>
                        
                        <!-- Settings Section -->
                        <div v-if="canAccessSettings" class="relative group">
                            <template v-if="sidebarCollapsed">
                                <div class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 cursor-pointer">
                                    <Cog8ToothIcon class="h-6 w-6" />
                                </div>
                                
                                <!-- Side dropdown for collapsed state -->
                                <div class="absolute left-full top-0 w-64 bg-white shadow-lg rounded-md border border-gray-200 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none group-hover:pointer-events-auto z-10" style="margin-left: 8px;">
                                    <div class="p-2">
                                        <div class="font-medium text-gray-800 px-2 py-1 border-b border-gray-200 mb-2">Pengaturan</div>
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('general-settings.index')"
                                            :active="route().current('general-settings.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            General
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
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
                                        <NavLink v-if="canViewPartners" :href="route('partners.index')"
                                            :active="route().current('partners.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Partner Bisnis
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('shipping-providers.index')"
                                            :active="route().current('shipping-providers.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Penyedia Pengiriman
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewRoles"
                                            :href="route('roles.index')" 
                                            :active="
                                                route().current('roles.*')
                                                || route().current('users.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Hak Akses Pengguna
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('company-bank-accounts.index')"
                                            :active="route().current('company-bank-accounts.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Rekening Bank
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('gl-event-configurations.index')"
                                            :active="route().current('gl-event-configurations.*')"
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Konfigurasi GL Event
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewCompanies"
                                            :href="route('tax-jurisdictions.index')" 
                                            :active="
                                                route().current('tax-jurisdictions.*')
                                                || route().current('tax-components.*')
                                                || route().current('tax-categories.*')
                                                || route().current('tax-rules.*')
                                            " 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Pajak
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewCompanies"
                                            :href="route('document-templates.index')" 
                                            :active="route().current('document-templates.*')" 
                                            class="flex items-center px-2 py-1 text-sm hover:bg-gray-50 rounded"
                                        >
                                            Template Dokumen
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
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('general-settings.index')"
                                            :active="route().current('general-settings.*')"
                                            class="flex items-center"
                                        >
                                            General
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
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
                                        <NavLink v-if="canViewPartners" :href="route('partners.index')"
                                            :active="route().current('partners.*')"
                                            class="flex items-center"
                                        >
                                            Partner Bisnis
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('shipping-providers.index')"
                                            :active="route().current('shipping-providers.*')"
                                            class="flex items-center"
                                        >
                                            Penyedia Pengiriman
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewRoles"
                                            :href="route('roles.index')"
                                            :active="
                                                route().current('roles.*')
                                                || route().current('users.*')
                                            "
                                            class="flex items-center"
                                        >
                                            Hak Akses Pengguna
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('company-bank-accounts.index')"
                                            :active="route().current('company-bank-accounts.*')"
                                            class="flex items-center"
                                        >
                                            Rekening Bank
                                        </NavLink>
                                        <NavLink
                                            v-if="canViewCompanies"
                                            :href="route('gl-event-configurations.index')"
                                            :active="route().current('gl-event-configurations.*')"
                                            class="flex items-center"
                                        >
                                            Konfigurasi GL Event
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewCompanies"
                                            :href="route('tax-jurisdictions.index')" 
                                            :active="
                                                route().current('tax-jurisdictions.*')
                                                || route().current('tax-components.*')
                                                || route().current('tax-categories.*')
                                                || route().current('tax-rules.*')
                                            " 
                                            class="flex items-center"
                                        >
                                            Pajak
                                        </NavLink>
                                        <NavLink 
                                            v-if="canViewCompanies"
                                            :href="route('document-templates.index')" 
                                            :active="route().current('document-templates.*')" 
                                            class="flex items-center"
                                        >
                                            Template Dokumen
                                        </NavLink>
                                    </DisclosurePanel>
                                </Disclosure>
                            </template>
                        </div>

                        <!-- Help Link at Bottom -->
                        <div class="relative group mt-6 pt-4 border-t border-gray-200">
                            <template v-if="sidebarCollapsed">
                                <button 
                                    @click="showHelp = true"
                                    class="flex items-center justify-center p-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 hover:bg-gray-50 cursor-pointer w-full"
                                    title="Bantuan"
                                >
                                    <QuestionMarkCircleIcon class="h-6 w-6" />
                                </button>
                                
                                <!-- Tooltip for collapsed state -->
                                <div class="absolute left-full top-0 ml-2 px-2 py-1 bg-gray-800 text-white text-sm rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50">
                                    Bantuan
                                </div>
                            </template>

                            <template v-else>
                                <button 
                                    @click="showHelp = true"
                                    class="flex items-center w-full text-left px-2 py-2 text-sm font-medium text-gray-600 rounded-md hover:text-main-700 hover:bg-gray-50 focus:outline-none"
                                >
                                    <QuestionMarkCircleIcon class="h-6 w-6 mr-2" />
                                    <span>Bantuan</span>
                                </button>
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
                    <div class="mx-auto pt-8 pb-6 pl-10 lg:pr-10">
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
                <main class="px-0 md:px-10 pb-12">
                    <slot />
                </main>
            </div>
        </div>
    </div>
    
    <!-- Onboarding Tour -->
    <OnboardingTour 
        :show="showOnboarding" 
        :initial-step="onboardingStep"
    />

    <!-- Help Documentation -->
    <HelpDocumentation
        :show="showHelp"
        @close="showHelp = false"
    />
</template>