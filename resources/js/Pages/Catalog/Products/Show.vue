<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppEditButton from '@/Components/AppEditButton.vue';
import AppDeleteButton from '@/Components/AppDeleteButton.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import AppBackLink from '@/Components/AppBackLink.vue';
import { ref } from 'vue';

const props = defineProps({
    product: Object,
    template: Object,
    group: String,
    kindLabel: String,
});

const form = useForm({});
const showDeleteConfirmation = ref(false);

const deleteProduct = () => {
    form.delete(route('catalog.products.destroy', props.product.id), {
        onSuccess: () => {
            showDeleteConfirmation.value = false;
        },
        onError: () => {
            showDeleteConfirmation.value = false;
        }
    });
};

function formatCostModel(model) {
    const labels = {
        'inventory_layer': 'Inventory Layer',
        'direct_expense_per_sale': 'Direct Expense',
        'job_costing': 'Job Costing',
        'asset_usage_costing': 'Asset Usage',
        'prepaid_consumption': 'Prepaid',
        'hybrid': 'Hybrid',
        'none': 'None',
    };
    return labels[model] || model;
}

function formatCapability(cap) {
    const labels = {
        'inventory_tracked': 'Inventori Terlacak',
        'variantable': 'Varian',
        'bookable': 'Booking',
        'rental': 'Rental',
        'serialized': 'Serial',
        'package': 'Paket',
    };
    return labels[cap.capability || cap] || cap.capability || cap;
}
</script>

<template>
    <Head :title="`Detail: ${product.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2>Detail Produk</h2>
        </template>

        <div>
            <div class="mx-auto">
                <div class="bg-white overflow-auto shadow-sm sm:rounded-s border-y border-l border-gray-200">
                    <div class="p-6 text-gray-900">
                        <div class="mb-6">
                            <AppBackLink :href="route('catalog.products.index', { group })" text="Kembali ke Daftar" />
                        </div>

                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="text-lg font-bold">{{ product.code }} - {{ product.name }}</h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded">
                                        {{ kindLabel }}
                                    </span>
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">
                                        {{ formatCostModel(product.cost_model) }}
                                    </span>
                                    <span
                                        v-for="cap in product.capabilities"
                                        :key="cap.id"
                                        class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded"
                                    >
                                        {{ formatCapability(cap) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <Link :href="route('catalog.products.edit', product.id)" class="mr-1">
                                    <AppEditButton title="Edit" />
                                </Link>
                                <AppDeleteButton @click="showDeleteConfirmation = true" title="Hapus" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 *:py-1 text-sm mb-6">
                            <div>
                                <p class="font-semibold">Kategori:</p>
                                <p>{{ product.category?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">UOM Default:</p>
                                <p>{{ product.default_uom?.code ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Kategori Pajak:</p>
                                <p>{{ product.tax_category?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="font-semibold">Status:</p>
                                <p>
                                    <span v-if="product.is_active" class="text-green-600">Aktif</span>
                                    <span v-else class="text-red-600">Tidak Aktif</span>
                                </p>
                            </div>
                            <div class="col-span-2">
                                <p class="font-semibold">Perusahaan:</p>
                                <p>{{ product.companies?.length ? product.companies.map(c => c.name).join(', ') : '-' }}</p>
                            </div>
                        </div>

                        <!-- Attributes -->
                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Atribut</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Kode</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(value, key) in product.attrs_json || {}" :key="key">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ key }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ value }}</td>
                                    </tr>
                                    <tr v-if="!product.attrs_json || Object.keys(product.attrs_json).length === 0">
                                        <td colspan="2" class="border border-gray-300 px-1.5 py-1.5 text-center text-gray-500">Tidak ada atribut.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Variants (if applicable) -->
                        <div v-if="product.variants && product.variants.length > 0" class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Varian</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">SKU</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Atribut</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Track Inventory</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="variant in product.variants" :key="variant.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ variant.sku }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">
                                            <span v-for="(val, key) in variant.attrs_json" :key="key" class="inline-block mr-2 text-xs bg-gray-100 px-2 py-0.5 rounded">
                                                {{ key }}: {{ val }}
                                            </span>
                                        </td>
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ variant.track_inventory ? 'Ya' : 'Tidak' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Price List Items -->
                        <div v-if="product.price_list_items && product.price_list_items.length > 0" class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Daftar Harga</h4>
                            <table class="w-full border-collapse border border-gray-300 text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-left">Price List</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right">Min Qty</th>
                                        <th class="border border-gray-300 px-1.5 py-1.5 text-right">Harga</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in product.price_list_items" :key="item.id">
                                        <td class="border border-gray-300 px-1.5 py-1.5">{{ item.price_list?.name ?? '-' }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ item.min_qty }}</td>
                                        <td class="border border-gray-300 px-1.5 py-1.5 text-right">{{ Number(item.price).toLocaleString('id-ID') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteConfirmation"
            title="Hapus Produk"
            message="Apakah Anda yakin ingin menghapus produk ini?"
            @close="showDeleteConfirmation = false"
            @confirm="deleteProduct"
        />
    </AuthenticatedLayout>
</template>
