<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AppBackLink from '@/Components/AppBackLink.vue';
import AppPrimaryButton from '@/Components/AppPrimaryButton.vue';
import { formatNumber } from '@/utils/numberFormat';

const props = defineProps({
    priceList: Object,
    filters: Object,
});

function formatDate(date) {
    return date ? new Date(date).toLocaleDateString('id-ID') : '-';
}
</script>

<template>
    <Head :title="`Kelompok Harga: ${priceList.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2>{{ priceList.name }}</h2>
                <AppPrimaryButton :href="route('catalog.price-lists.edit', priceList.id)" as="a">
                    Ubah
                </AppPrimaryButton>
            </div>
        </template>

        <div class="mx-auto">
            <div class="mb-4">
                <AppBackLink :href="route('catalog.price-lists.index', filters)" text="Kembali ke Kelompok Harga" />
            </div>

            <div class="bg-white shadow-sm sm:rounded border border-gray-200 p-6">
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Informasi Umum</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Kode:</dt>
                                <dd class="w-2/3 font-medium">{{ priceList.code }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Nama:</dt>
                                <dd class="w-2/3">{{ priceList.name }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Perusahaan:</dt>
                                <dd class="w-2/3">{{ priceList.company?.name ?? '-' }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Mata Uang:</dt>
                                <dd class="w-2/3">{{ priceList.currency?.code ?? '-' }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Kanal:</dt>
                                <dd class="w-2/3">{{ priceList.channel ?? '-' }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Grup Partner:</dt>
                                <dd class="w-2/3">{{ priceList.partner_group?.name ?? '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Periode & Status</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Berlaku Dari:</dt>
                                <dd class="w-2/3">{{ formatDate(priceList.valid_from) }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Berlaku Sampai:</dt>
                                <dd class="w-2/3">{{ formatDate(priceList.valid_to) }}</dd>
                            </div>
                            <div class="flex">
                                <dt class="w-1/3 text-gray-500">Status:</dt>
                                <dd class="w-2/3">
                                    <span
                                        class="px-2 py-0.5 rounded text-xs"
                                        :class="priceList.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                    >
                                        {{ priceList.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Price List Items -->
                <div class="mt-6">
                    <h3 class="text-lg font-semibold mb-3">Item Harga</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 uppercase text-xs border-b">
                                    <th class="py-2">Produk / Varian</th>
                                    <th class="py-2">UOM</th>
                                    <th class="py-2">Min Qty</th>
                                    <th class="py-2 text-right">Harga</th>
                                    <th class="py-2">Termasuk Pajak</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in priceList.items" :key="item.id" class="border-b last:border-b-0">
                                    <td class="py-3">
                                        <div v-if="item.product_variant">{{ item.product_variant.sku }}</div>
                                        <div v-else-if="item.product">{{ item.product.name }}</div>
                                        <div v-else>-</div>
                                    </td>
                                    <td class="py-3">{{ item.uom?.name ?? '-' }}</td>
                                    <td class="py-3">{{ formatNumber(item.min_qty) }}</td>
                                    <td class="py-3 text-right font-medium">{{ formatNumber(item.price) }}</td>
                                    <td class="py-3">{{ item.tax_included ? 'Ya' : 'Tidak' }}</td>
                                </tr>
                                <tr v-if="!priceList.items?.length">
                                    <td colspan="5" class="py-4 text-center text-gray-500">Belum ada item harga.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
