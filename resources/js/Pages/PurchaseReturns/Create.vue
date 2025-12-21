<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import PurchaseReturnForm from './Partials/PurchaseReturnForm.vue';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    companies: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Array,
        default: () => [],
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    goodsReceipts: {
        type: Array,
        default: () => [],
    },
    selectedCompanyId: Number,
    selectedBranchId: Number,
    selectedSupplierId: Number,
    selectedGoodsReceipt: {
        type: Object,
        default: null,
    },
    reasonOptions: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Buat Retur Pembelian" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Buat Retur Pembelian</h2>
        </template>

        <div class="mx-auto">
            <div class="bg-white overflow-auto shadow-sm sm:rounded border border-gray-200">
                <div class="p-6 text-gray-900 space-y-6">
                    <AppBackLink :href="route('purchase-returns.index', filters)" text="Kembali ke Daftar Retur" />

                    <PurchaseReturnForm
                        :filters="filters"
                        :companies="companies"
                        :branches="branches"
                        :suppliers="suppliers"
                        :goods-receipts="goodsReceipts"
                        :selected-company-id="selectedCompanyId"
                        :selected-branch-id="selectedBranchId"
                        :selected-supplier-id="selectedSupplierId"
                        :selected-goods-receipt="selectedGoodsReceipt"
                        :reason-options="reasonOptions"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
