<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    componentScrap: Object,
    filters: Object,
});
</script>

<template>
    <Head title="Detail Component Scrap" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Component Scrap</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('component-scraps.index', filters)" text="Kembali ke Daftar Component Scraps" />
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-bold">Component Scrap #{{ componentScrap.id }}</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-4 *:py-1 text-sm">
                            <div>
                                <p class="font-semibold">Tanggal Scrap:</p>
                                <p>{{ componentScrap.scrap_date ? new Date(componentScrap.scrap_date).toLocaleDateString('id-ID') : '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Work Order:</p>
                                <p>
                                    <Link :href="route('work-orders.show', componentScrap.work_order?.id)" class="text-blue-600 hover:underline">
                                        {{ componentScrap.work_order?.wo_number }}
                                    </Link>
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Komponen:</p>
                                <p>{{ componentScrap.component_product?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Varian:</p>
                                <p v-if="componentScrap.component_product_variant">
                                    {{ componentScrap.component_product_variant.name }} ({{ componentScrap.component_product_variant.sku }})
                                </p>
                                <p v-else class="text-gray-400">-</p>
                            </div>
                            <div>
                                <p class="font-semibold">Quantity Scrap:</p>
                                <p>{{ formatNumber(componentScrap.scrap_quantity) }} {{ componentScrap.uom?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Alasan Scrap:</p>
                                <p>{{ componentScrap.scrap_reason }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Backflush:</p>
                                <p>
                                    <span v-if="componentScrap.is_backflush" class="text-green-600">Ya</span>
                                    <span v-else class="text-gray-400">Tidak</span>
                                </p>
                            </div>
                            <div>
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ componentScrap.work_order?.branch?.branch_group?.company?.name }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Cabang:</p>
                                <p>{{ componentScrap.work_order?.branch?.name }}</p>
                            </div>
                            <div v-if="componentScrap.finished_goods_receipt">
                                <p class="font-semibold">Finished Goods Receipt:</p>
                                <p>
                                    <Link :href="route('finished-goods-receipts.show', componentScrap.finished_goods_receipt?.id)" class="text-blue-600 hover:underline">
                                        {{ componentScrap.finished_goods_receipt?.receipt_number }}
                                    </Link>
                                </p>
                            </div>
                            <div v-if="componentScrap.component_issue_line">
                                <p class="font-semibold">Component Issue Line:</p>
                                <p>
                                    <Link :href="route('component-issues.show', componentScrap.component_issue_line?.component_issue_id)" class="text-blue-600 hover:underline">
                                        View Component Issue
                                    </Link>
                                </p>
                            </div>
                            <div v-if="componentScrap.user">
                                <p class="font-semibold">Dibuat Oleh:</p>
                                <p>{{ componentScrap.user?.name }}</p>
                            </div>
                            <div v-if="componentScrap.notes" class="col-span-2">
                                <p class="font-semibold">Catatan:</p>
                                <p>{{ componentScrap.notes }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

